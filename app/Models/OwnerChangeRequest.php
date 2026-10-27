<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\OwnerChangeStatus;
use App\Enums\OwnerChangeType;
use Database\Factories\OwnerChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property OwnerChangeType $type
 * @property array<string, mixed> $payload
 * @property OwnerChangeStatus $status
 * @property Carbon|null $reviewed_at
 */
#[Fillable(['property_id', 'requested_by', 'type', 'target_type', 'target_id', 'payload', 'status', 'reviewed_by', 'decision_note', 'reviewed_at'])]
class OwnerChangeRequest extends Model
{
    /** @use HasFactory<OwnerChangeRequestFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => OwnerChangeType::class,
            'payload' => 'array',
            'status' => OwnerChangeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
