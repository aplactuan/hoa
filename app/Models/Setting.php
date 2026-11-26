<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PenaltyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $dues_rate_per_sqm
 * @property string $penalty_percent
 * @property string $membership_fee
 * @property PenaltyType $penalty_type
 * @property string $penalty_fixed_amount
 * @property int $due_day
 * @property int $grace_days
 * @property int $expiry_warning_days
 * @property string $hoa_name
 * @property string $hoa_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'dues_rate_per_sqm',
    'penalty_percent',
    'membership_fee',
    'penalty_type',
    'penalty_fixed_amount',
    'due_day',
    'grace_days',
    'expiry_warning_days',
    'hoa_name',
    'hoa_address',
])]
class Setting extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dues_rate_per_sqm' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
            'membership_fee' => 'decimal:2',
            'penalty_type' => PenaltyType::class,
            'penalty_fixed_amount' => 'decimal:2',
            'due_day' => 'integer',
            'grace_days' => 'integer',
            'expiry_warning_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting instanceof self) {
            return $setting;
        }

        return static::query()->create([
            'dues_rate_per_sqm' => 10,
            'penalty_percent' => 2,
            'membership_fee' => 0,
            'penalty_type' => PenaltyType::Percent,
            'penalty_fixed_amount' => 0,
            'due_day' => 15,
            'grace_days' => 0,
            'expiry_warning_days' => 45,
            'hoa_name' => (string) config('app.name'),
            'hoa_address' => '',
        ]);
    }
}
