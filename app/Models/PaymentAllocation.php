<?php

namespace App\Models;

use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $amount
 */
#[Fillable(['payment_id', 'allocatable_type', 'allocatable_id', 'amount'])]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
