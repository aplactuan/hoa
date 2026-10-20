<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\CorrespondenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['code', 'property_id', 'type', 'subject', 'sender', 'recipient', 'notes', 'status', 'acknowledged_on', 'related_type', 'related_id', 'created_by'])]
class Correspondence extends Model
{
    /** @use HasFactory<CorrespondenceFactory> */
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return ['acknowledged_on' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
