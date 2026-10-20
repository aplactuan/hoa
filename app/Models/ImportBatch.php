<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'filename', 'checksum', 'row_count', 'imported_count', 'error_count', 'reconciled_amount', 'status', 'created_by'])]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['row_count' => 'integer', 'imported_count' => 'integer', 'error_count' => 'integer', 'reconciled_amount' => 'decimal:2'];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
