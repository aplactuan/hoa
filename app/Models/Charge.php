<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['code', 'property_id', 'charge_type_id', 'source_type', 'source_id', 'description', 'amount', 'due_on', 'status', 'waiver_reason', 'waiver_requested_by', 'waived_by', 'created_by', 'updated_by'])]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_on' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }
}
