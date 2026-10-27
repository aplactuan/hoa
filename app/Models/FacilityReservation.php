<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\FacilityReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $event_on
 * @property string $fee
 * @property string $deposit
 * @property Carbon|null $refunded_on
 * @property string|null $refunded_amount
 */
#[Fillable(['code', 'property_id', 'facility_id', 'event_on', 'starts_at', 'ends_at', 'purpose', 'guests', 'fee', 'deposit', 'approval_status', 'payment_status', 'approved_by', 'fee_charge_id', 'deposit_charge_id', 'refunded_on', 'refunded_amount', 'refund_reference', 'refunded_by', 'created_by', 'updated_by'])]
class FacilityReservation extends Model
{
    /** @use HasFactory<FacilityReservationFactory> */
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return ['event_on' => 'date', 'fee' => 'decimal:2', 'deposit' => 'decimal:2', 'refunded_on' => 'date', 'refunded_amount' => 'decimal:2', 'guests' => 'integer'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function feeCharge(): BelongsTo
    {
        return $this->belongsTo(Charge::class, 'fee_charge_id');
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function depositCharge(): BelongsTo
    {
        return $this->belongsTo(Charge::class, 'deposit_charge_id');
    }
}
