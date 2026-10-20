<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\ServiceCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'property_id', 'request_category_id', 'filed_by', 'description', 'priority', 'status', 'assigned_to', 'action_taken', 'resolved_on', 'created_by', 'updated_by'])]
class ServiceCase extends Model
{
    /** @use HasFactory<ServiceCaseFactory> */
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return ['resolved_on' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RequestCategory::class, 'request_category_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CaseUpdate::class);
    }
}
