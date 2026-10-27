<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\DuesStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $regular
 * @property string $special
 * @property string $penalty
 * @property string $previous_balance
 * @property string $credits
 * @property Carbon $due_on
 */
#[Fillable(['code', 'property_id', 'billing_period_id', 'regular', 'special', 'penalty', 'previous_balance', 'credits', 'due_on', 'remarks', 'created_by'])]
class DuesStatement extends Model
{
    /** @use HasFactory<DuesStatementFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['regular' => 'decimal:2', 'special' => 'decimal:2', 'penalty' => 'decimal:2', 'previous_balance' => 'decimal:2', 'credits' => 'decimal:2', 'due_on' => 'date'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<BillingPeriod, $this>
     */
    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    /**
     * @return MorphMany<PaymentAllocation, $this>
     */
    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    /**
     * @return HasMany<StatementAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(StatementAdjustment::class);
    }
}
