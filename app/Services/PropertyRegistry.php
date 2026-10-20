<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Vehicle;
use App\Models\VehicleSticker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyRegistry
{
    public function __construct(private DisplayCode $codes, private BillingService $billing) {}

    /** @param array<string, mixed> $attributes */
    public function createProperty(array $attributes, ?int $userId = null): Property
    {
        return DB::transaction(function () use ($attributes, $userId): Property {
            $code = $this->codes->next('property');
            $number = substr($code, 4);

            return Property::query()->create(array_merge($attributes, [
                'code' => $code,
                'account_no' => 'ACC-'.$number,
                'membership_no' => 'MEM-'.$number,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        });
    }

    /** @param array<string, mixed> $attributes */
    public function transferOwnership(Property $property, array $attributes, ?int $userId = null): Owner
    {
        return DB::transaction(function () use ($property, $attributes, $userId): Owner {
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);
            $startedOn = (string) $attributes['started_on'];
            $currentOwners = $property->owners()->where('status', 'Current')->lockForUpdate()->get();
            $isCoOwner = ($attributes['ownership_type'] ?? 'Sole Owner') === 'Co-Owner';

            if ($isCoOwner && $currentOwners->contains(fn (Owner $owner): bool => $owner->ownership_type !== 'Co-Owner')) {
                throw ValidationException::withMessages(['ownership_type' => 'All current owners must be co-owners.']);
            }

            if (! $isCoOwner) {
                foreach ($currentOwners as $currentOwner) {
                    if ($currentOwner->started_on->toDateString() > $startedOn) {
                        throw ValidationException::withMessages(['started_on' => 'Transfer date cannot precede current ownership.']);
                    }

                    $currentOwner->update(['status' => 'Former', 'ended_on' => $startedOn, 'updated_by' => $userId]);
                }
            }

            $owner = $property->owners()->create(array_merge($attributes, [
                'code' => $this->codes->next('owner'),
                'status' => 'Current',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            $property->update(['current_owner_id' => $owner->id, 'updated_by' => $userId]);

            return $owner;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function moveIn(Property $property, array $attributes, ?int $userId = null): Resident
    {
        return DB::transaction(function () use ($property, $attributes, $userId): Resident {
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);

            if ($property->current_resident_id !== null) {
                throw ValidationException::withMessages(['property_id' => 'Move out the current resident first.']);
            }

            $resident = $property->residents()->create(array_merge($attributes, [
                'code' => $this->codes->next('resident'),
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
            $property->update(['current_resident_id' => $resident->id, 'status' => 'Occupied', 'updated_by' => $userId]);

            return $resident;
        });
    }

    public function moveOut(Resident $resident, string $date, ?int $userId = null): void
    {
        DB::transaction(function () use ($resident, $date, $userId): void {
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);

            if ($resident->moved_in_on->toDateString() > $date) {
                throw ValidationException::withMessages(['moved_out_on' => 'Move-out date cannot precede move-in.']);
            }

            $resident->update(['status' => 'Moved Out', 'moved_out_on' => $date, 'updated_by' => $userId]);
            $resident->familyMembers()->where('status', 'Active')->get()->each(
                fn ($member) => $member->update(['status' => 'Moved Out', 'moved_out_on' => $date, 'updated_by' => $userId])
            );
            $resident->houseStaff()->where('status', 'Active')->get()->each(
                fn ($staff) => $staff->update(['status' => 'Inactive', 'ended_on' => $date, 'updated_by' => $userId])
            );

            $property = Property::query()->lockForUpdate()->findOrFail($resident->property_id);

            if ($property->current_resident_id === $resident->id) {
                $property->update(['current_resident_id' => null, 'status' => 'Vacant', 'updated_by' => $userId]);

                foreach ($property->vehicles()->with('stickers')->get() as $vehicle) {
                    foreach ($vehicle->stickers->where('status', 'Active') as $sticker) {
                        $sticker->update(['status' => 'Cancelled', 'updated_by' => $userId]);
                    }
                }
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function registerVehicle(Property $property, array $attributes, ?int $userId = null): Vehicle
    {
        $plate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $attributes['plate_no']) ?? '');

        if ($plate === '' || Vehicle::query()->where('normalized_plate', $plate)->where('status', 'Active')->exists()) {
            throw ValidationException::withMessages(['plate_no' => 'An active vehicle with this plate already exists.']);
        }

        return DB::transaction(fn (): Vehicle => $property->vehicles()->create(array_merge($attributes, [
            'code' => $this->codes->next('vehicle'),
            'normalized_plate' => $plate,
            'status' => 'Active',
            'created_by' => $userId,
            'updated_by' => $userId,
        ])));
    }

    public function issueSticker(Vehicle $vehicle, string $number, int $year, ?int $userId = null): VehicleSticker
    {
        if ($year < 2000 || $year > 2100 || trim($number) === '') {
            throw ValidationException::withMessages(['year' => 'Enter a valid sticker number and year.']);
        }

        return DB::transaction(function () use ($vehicle, $number, $year, $userId): VehicleSticker {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);

            if ($vehicle->stickers()->where('year', $year)->exists()) {
                throw ValidationException::withMessages(['year' => 'A sticker already exists for this vehicle and year.']);
            }

            $sticker = $vehicle->stickers()->create([
                'code' => $this->codes->next('sticker'),
                'sticker_no' => trim($number),
                'year' => $year,
                'issued_on' => now()->toDateString(),
                'expires_on' => now()->setYear($year)->endOfYear()->toDateString(),
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $charge = $this->billing->createCharge($vehicle->property, 'Car Sticker '.$year, '600.00', now()->toDateString(), $userId);
            $charge->update(['source_type' => VehicleSticker::class, 'source_id' => $sticker->id]);
            $sticker->update(['charge_id' => $charge->id]);

            return $sticker;
        });
    }
}
