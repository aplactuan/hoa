<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\CaseUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_case_id', 'user_id', 'note'])]
class CaseUpdate extends Model
{
    /** @use HasFactory<CaseUpdateFactory> */
    use Auditable, HasAttachments, HasFactory;

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }
}
