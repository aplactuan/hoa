<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $paid_on
 * @property string $amount
 */
#[Fillable(['code', 'property_id', 'or_no', 'paid_on', 'amount', 'method', 'reference_no', 'status', 'remarks', 'void_reason', 'received_by', 'void_requested_by', 'voided_by'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
