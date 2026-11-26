<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\MonthlyFeeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The flat monthly fee charged once per owner, effective from a given month onward.
 *
 * @property int $id
 * @property string $amount
 * @property Carbon $effective_from
 * @property string|null $remarks
 */
#[Fillable(['amount', 'effective_from', 'remarks', 'created_by'])]
class MonthlyFeeRate extends Model
{
    /** @use HasFactory<MonthlyFeeRateFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_from' => 'date'];
    }

    public static function amountFor(int $year, int $month): string
    {
        $amount = static::query()
            ->whereDate('effective_from', '<=', sprintf('%04d-%02d-01', $year, $month))
            ->latest('effective_from')
            ->value('amount');

        return $amount === null ? '0.00' : (string) $amount;
    }
}
