<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\ViolationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $fine
 */
#[Fillable(['code', 'property_id', 'violation_type_id', 'description', 'noticed_on', 'deadline_on', 'fine', 'status', 'resolution', 'resolved_on', 'charge_id', 'created_by', 'updated_by'])]
class Violation extends Model
{
    /** @use HasFactory<ViolationFactory> */
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return ['noticed_on' => 'date', 'deadline_on' => 'date', 'resolved_on' => 'date', 'fine' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<ViolationType, $this>
     */
    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
