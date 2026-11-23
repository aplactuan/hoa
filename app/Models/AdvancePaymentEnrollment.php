<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\AdvancePaymentEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records an owner's qualifying prepayment for a promo and locks the monthly fee for the covered months.
 *
 * @property int $id
 * @property int $advance_payment_promo_id
 * @property int $property_id
 * @property int $payment_id
 * @property string $monthly_fee
 * @property string $discount_total
 */
#[Fillable(['advance_payment_promo_id', 'property_id', 'payment_id', 'monthly_fee', 'discount_total'])]
class AdvancePaymentEnrollment extends Model
{
    /** @use HasFactory<AdvancePaymentEnrollmentFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['monthly_fee' => 'decimal:2', 'discount_total' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<AdvancePaymentPromo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(AdvancePaymentPromo::class, 'advance_payment_promo_id');
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
