<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DiscountType;
use Database\Factories\AdvancePaymentPromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A discount for owners who prepay a range of months on or before a deadline.
 *
 * @property int $id
 * @property string $name
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property Carbon $pay_by
 * @property Carbon $covers_from
 * @property Carbon $covers_to
 * @property bool $is_active
 */
#[Fillable(['name', 'discount_type', 'discount_value', 'pay_by', 'covers_from', 'covers_to', 'is_active', 'created_by'])]
class AdvancePaymentPromo extends Model
{
    /** @use HasFactory<AdvancePaymentPromoFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'pay_by' => 'date',
            'covers_from' => 'date',
            'covers_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AdvancePaymentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(AdvancePaymentEnrollment::class);
    }

    /**
     * @return list<array{year: int, month: int}>
     */
    public function coveredMonths(): array
    {
        $months = [];
        $cursor = $this->covers_from->copy()->startOfMonth();
        $last = $this->covers_to->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    public function covers(int $year, int $month): bool
    {
        $period = sprintf('%04d-%02d-01', $year, $month);

        return $period >= $this->covers_from->copy()->startOfMonth()->toDateString()
            && $period <= $this->covers_to->copy()->startOfMonth()->toDateString();
    }

    public function label(): string
    {
        $discount = $this->discount_type === DiscountType::Percent
            ? rtrim(rtrim($this->discount_value, '0'), '.').'% off'
            : '₱'.$this->discount_value.' off';

        return $this->name.' ('.$discount.', '.$this->covers_from->format('M Y').'–'.$this->covers_to->format('M Y').', pay by '.$this->pay_by->toDateString().')';
    }
}
