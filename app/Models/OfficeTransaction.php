<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\OfficeTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $amount
 * @property Carbon|null $completed_on
 */
#[Fillable(['code', 'property_id', 'transaction_type_id', 'requested_by', 'description', 'amount', 'assigned_to', 'charge_id', 'status', 'completed_on', 'cancel_reason', 'created_by', 'updated_by'])]
class OfficeTransaction extends Model
{
    /** @use HasFactory<OfficeTransactionFactory> */
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'completed_on' => 'date'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<TransactionType, $this>
     */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
