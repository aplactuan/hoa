<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Property;
use App\Models\Section;
use App\Models\Street;
use App\Models\Vehicle;
use App\Support\BillingMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SpreadsheetImport
{
    public function __construct(private PropertyRegistry $registry, private BillingService $billing) {}

    /** @return array<string, list<string>> */
    public function templates(): array
    {
        return [
            'Streets and Phases' => ['street_name', 'phase'],
            'Properties' => ['block', 'lot', 'lot_area', 'house_no'],
            'Owners' => ['property_code', 'name', 'ownership_type', 'started_on', 'phone', 'email'],
            'Residents' => ['property_code', 'name', 'occupancy_type', 'moved_in_on', 'phone'],
            'Vehicles' => ['property_code', 'driver_name', 'type', 'plate_no', 'make', 'model', 'color'],
            'Opening Balances' => ['account_no', 'amount', 'as_of'],
        ];
    }

    public function preview(string $source, string $path, string $filename, ?int $userId): ImportBatch
    {
        [$rows, $errors] = $this->analyze($source, $path);

        return DB::transaction(function () use ($source, $path, $filename, $userId, $rows, $errors): ImportBatch {
            $batch = ImportBatch::query()->create([
                'source' => $source,
                'filename' => $filename,
                'checksum' => hash_file('sha256', $path),
                'row_count' => count($rows),
                'error_count' => count($errors),
                'created_by' => $userId,
            ]);

            foreach ($errors as $error) {
                $batch->errors()->create($error);
            }

            return $batch;
        });
    }

    public function commit(ImportBatch $batch, string $path, ?int $userId): ImportBatch
    {
        if ($batch->checksum !== hash_file('sha256', $path)) {
            throw ValidationException::withMessages(['file' => 'The previewed file has changed. Preview it again.']);
        }

        return DB::transaction(function () use ($batch, $path, $userId): ImportBatch {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            [$rows, $errors] = $this->analyze($batch->source, $path);

            if ($batch->status !== 'Previewed' || $errors !== [] || $batch->error_count > 0) {
                throw ValidationException::withMessages(['file' => 'Resolve all preview errors before importing.']);
            }

            $totalCents = 0;

            foreach ($rows as $row) {
                $totalCents += $this->importRow($batch->source, $row['data'], $userId);
            }

            $batch->update([
                'status' => 'Imported',
                'imported_count' => count($rows),
                'reconciled_amount' => $batch->source === 'Opening Balances' ? BillingMath::decimal($totalCents) : null,
            ]);

            return $batch;
        });
    }

    /** @return array{list<array{number: int, data: array<string, string>}>, list<array{row_number: int, message: string}>} */
    private function analyze(string $source, string $path): array
    {
        $headers = $this->templates()[$source] ?? null;

        if ($headers === null) {
            throw ValidationException::withMessages(['source' => 'Choose an import type.']);
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        try {
            $actualHeaders = array_map(fn ($value): string => strtolower(trim((string) $value)), fgetcsv($stream, 0, ',', '"', '') ?: []);

            if ($actualHeaders !== $headers) {
                throw ValidationException::withMessages(['file' => 'CSV columns must be: '.implode(', ', $headers)]);
            }

            $rows = [];
            $errors = [];
            $seen = [];
            $number = 1;

            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $number++;

                if ($values === [null] || $values === ['']) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    $errors[] = ['row_number' => $number, 'message' => 'Column count does not match the header.'];

                    continue;
                }

                $data = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
                $validator = Validator::make($data, $this->rules($source));

                if ($validator->fails()) {
                    $errors[] = ['row_number' => $number, 'message' => $validator->errors()->first()];

                    continue;
                }

                $key = match ($source) {
                    'Streets and Phases' => $data['phase'].'-'.$data['street_name'],
                    'Properties' => $data['block'].'-'.$data['lot'],
                    'Vehicles' => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $data['plate_no']) ?? ''),
                    'Opening Balances' => $data['account_no'],
                    'Residents' => $data['property_code'],
                    default => $data['property_code'].'-'.$data['name'],
                };

                if (isset($seen[$key]) || $this->alreadyExists($source, $data)) {
                    $errors[] = ['row_number' => $number, 'message' => 'Duplicate or existing record: '.$key];

                    continue;
                }

                $seen[$key] = true;
                $rows[] = ['number' => $number, 'data' => $data];
            }

            if ($rows === [] && $errors === []) {
                throw ValidationException::withMessages(['file' => 'The CSV file has no data rows.']);
            }

            return [$rows, $errors];
        } finally {
            fclose($stream);
        }
    }

    /** @return array<string, list<string>> */
    private function rules(string $source): array
    {
        return match ($source) {
            'Streets and Phases' => ['street_name' => ['required', 'string', 'max:255'], 'phase' => ['required', 'string', 'max:255']],
            'Properties' => ['block' => ['required', 'integer', 'min:1'], 'lot' => ['required', 'integer', 'min:1'], 'lot_area' => ['required', 'numeric', 'gt:0'], 'house_no' => ['nullable', 'string', 'max:100']],
            'Owners' => ['property_code' => ['required', 'exists:properties,code'], 'name' => ['required', 'string', 'max:255'], 'ownership_type' => ['required', 'in:Sole Owner,Co-Owner'], 'started_on' => ['required', 'date_format:Y-m-d'], 'phone' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email']],
            'Residents' => ['property_code' => ['required', 'exists:properties,code'], 'name' => ['required', 'string', 'max:255'], 'occupancy_type' => ['required', 'in:Owner,Tenant,Caretaker'], 'moved_in_on' => ['required', 'date_format:Y-m-d'], 'phone' => ['nullable', 'string', 'max:100']],
            'Vehicles' => ['property_code' => ['required', 'exists:properties,code'], 'driver_name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'], 'plate_no' => ['required', 'string', 'max:100'], 'make' => ['nullable', 'string'], 'model' => ['nullable', 'string'], 'color' => ['nullable', 'string']],
            default => ['account_no' => ['required', 'exists:properties,account_no'], 'amount' => ['required', 'numeric', 'min:0'], 'as_of' => ['required', 'date_format:Y-m-d']],
        };
    }

    /** @param array<string, string> $data */
    private function alreadyExists(string $source, array $data): bool
    {
        return match ($source) {
            'Streets and Phases' => Street::query()
                ->where('name', $data['street_name'])
                ->whereHas('section', fn ($query) => $query->where('name', $data['phase']))
                ->exists(),
            'Properties' => Property::query()->where('block', $data['block'])->where('lot', $data['lot'])->exists(),
            'Owners' => false,
            'Residents' => Property::query()->where('code', $data['property_code'])->whereNotNull('current_resident_id')->exists(),
            'Vehicles' => Vehicle::query()->where('normalized_plate', strtoupper(preg_replace('/[^A-Z0-9]/i', '', $data['plate_no']) ?? ''))->where('status', 'Active')->exists(),
            default => Property::query()->where('account_no', $data['account_no'])
                ->where(fn ($query) => $query->whereHas('statements')->orWhereHas('payments')->orWhereHas('charges'))
                ->exists(),
        };
    }

    /** @param array<string, string> $data */
    private function importRow(string $source, array $data, ?int $userId): int
    {
        if ($source === 'Streets and Phases') {
            $phase = Section::query()->firstOrCreate(
                ['name' => $data['phase']],
                ['is_active' => true],
            );

            Street::query()->firstOrCreate(
                ['section_id' => $phase->id, 'name' => $data['street_name']],
                ['is_active' => true],
            );

            return 0;
        }

        if ($source === 'Properties') {
            $this->registry->createProperty(['block' => $data['block'], 'lot' => $data['lot'], 'lot_area' => $data['lot_area'], 'house_no' => $data['house_no'] ?: null], $userId);

            return 0;
        }

        $property = Property::query()->where($source === 'Opening Balances' ? 'account_no' : 'code', $data[$source === 'Opening Balances' ? 'account_no' : 'property_code'])->firstOrFail();

        if ($source === 'Owners') {
            $this->registry->transferOwnership($property, ['name' => $data['name'], 'ownership_type' => $data['ownership_type'], 'started_on' => $data['started_on'], 'phone' => $data['phone'] ?: null, 'email' => $data['email'] ?: null], $userId);
        } elseif ($source === 'Residents') {
            $this->registry->moveIn($property, ['name' => $data['name'], 'occupancy_type' => $data['occupancy_type'], 'moved_in_on' => $data['moved_in_on'], 'phone' => $data['phone'] ?: null], $userId);
        } elseif ($source === 'Vehicles') {
            $this->registry->registerVehicle($property, ['driver_name' => $data['driver_name'], 'type' => $data['type'], 'plate_no' => $data['plate_no'], 'make' => $data['make'] ?: null, 'model' => $data['model'] ?: null, 'color' => $data['color'] ?: null], $userId);
        } elseif (BillingMath::cents($data['amount']) > 0) {
            $this->billing->createCharge($property, 'Balance forward', $data['amount'], $data['as_of'], $userId);
        }

        return $source === 'Opening Balances' ? BillingMath::cents($data['amount']) : 0;
    }
}
