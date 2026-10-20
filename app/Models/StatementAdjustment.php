<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\StatementAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dues_statement_id', 'amount', 'kind', 'reason', 'status', 'requested_by', 'approved_by', 'approved_at'])]
class StatementAdjustment extends Model
{
    /** @use HasFactory<StatementAdjustmentFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(DuesStatement::class, 'dues_statement_id');
    }
}
