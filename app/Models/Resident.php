<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'property_id', 'name', 'phone', 'email', 'relationship', 'occupancy_type', 'moved_in_on', 'moved_out_on', 'emergency_contact_name', 'emergency_contact_phone', 'status', 'remarks', 'created_by', 'updated_by'])]
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['moved_in_on' => 'date', 'moved_out_on' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function houseStaff(): HasMany
    {
        return $this->hasMany(HouseStaff::class);
    }
}
