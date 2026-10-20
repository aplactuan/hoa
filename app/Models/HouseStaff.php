<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\HouseStaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'property_id', 'resident_id', 'name', 'role', 'phone', 'started_on', 'ended_on', 'id_type', 'id_reference', 'pass_no', 'pass_expires_on', 'status', 'remarks', 'created_by', 'updated_by'])]
class HouseStaff extends Model
{
    /** @use HasFactory<HouseStaffFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'pass_expires_on' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
