<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DisplayCode
{
    /**
     * Issue the next display ID for a sequence.
     *
     * The row is incremented inside a transaction. The first statement writes,
     * so SQLite reserves the database and MySQL locks the row before the number
     * is read back. Two saves cannot receive the same ID.
     */
    public function next(string $sequence, ?string $period = null): string
    {
        $definition = $this->definition($sequence);
        $period = $definition['requires_period']
            ? ($period ?? now()->format('Ym'))
            : null;

        if ($period !== null && ! preg_match('/^\d{6}$/', $period)) {
            throw new InvalidArgumentException('A statement period must be a year and month, such as 202610.');
        }

        $key = $period === null ? $sequence : $sequence.':'.$period;

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 8000');
        }

        return DB::transaction(function () use ($key, $definition, $period): string {
            Sequence::query()->insertOrIgnore([
                'key' => $key,
                'prefix' => $definition['prefix'],
                'period' => $period,
                'pad_length' => $definition['pad'],
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = Sequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $number = $row->last_number + 1;
            $row->forceFill(['last_number' => $number])->save();

            return $this->format($definition['prefix'], $period, $number, $definition['pad']);
        });
    }

    /**
     * @return array{prefix: string, pad: int, requires_period: bool}
     */
    private function definition(string $sequence): array
    {
        return match ($sequence) {
            'property' => ['prefix' => 'PRP', 'pad' => 4, 'requires_period' => false],
            'owner' => ['prefix' => 'OWN', 'pad' => 4, 'requires_period' => false],
            'resident' => ['prefix' => 'RES', 'pad' => 4, 'requires_period' => false],
            'family_member' => ['prefix' => 'FAM', 'pad' => 4, 'requires_period' => false],
            'house_staff' => ['prefix' => 'STA', 'pad' => 4, 'requires_period' => false],
            'vehicle' => ['prefix' => 'VEH', 'pad' => 4, 'requires_period' => false],
            'sticker' => ['prefix' => 'STK', 'pad' => 4, 'requires_period' => false],
            'charge' => ['prefix' => 'CHG', 'pad' => 5, 'requires_period' => false],
            'payment' => ['prefix' => 'PAY', 'pad' => 5, 'requires_period' => false],
            'transaction' => ['prefix' => 'TRX', 'pad' => 5, 'requires_period' => false],
            'case' => ['prefix' => 'REQ', 'pad' => 5, 'requires_period' => false],
            'violation' => ['prefix' => 'VIO', 'pad' => 5, 'requires_period' => false],
            'correspondence' => ['prefix' => 'COR', 'pad' => 5, 'requires_period' => false],
            'reservation' => ['prefix' => 'FAC', 'pad' => 5, 'requires_period' => false],
            'statement' => ['prefix' => 'SOA', 'pad' => 3, 'requires_period' => true],
            'official_receipt' => ['prefix' => 'OR', 'pad' => 6, 'requires_period' => false],
            default => throw new InvalidArgumentException("Unknown display sequence [{$sequence}]."),
        };
    }

    private function format(string $prefix, ?string $period, int $number, int $pad): string
    {
        $formatted = str_pad((string) $number, $pad, '0', STR_PAD_LEFT);

        if ($period === null) {
            return $prefix.'-'.$formatted;
        }

        return $prefix.'-'.$period.'-'.$formatted;
    }
}
