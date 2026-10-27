<?php

namespace App\Models;

use Database\Factories\ImportErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_batch_id', 'row_number', 'message'])]
class ImportError extends Model
{
    /** @use HasFactory<ImportErrorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
