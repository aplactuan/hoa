<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'property_id', 'resident_id', 'name', 'relationship', 'phone', 'email', 'birth_date', 'occupation', 'is_emergency_contact', 'moved_in_on', 'moved_out_on', 'status', 'remarks', 'created_by', 'updated_by'])]
class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'moved_in_on' => 'date', 'moved_out_on' => 'date', 'is_emergency_contact' => 'boolean'];
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
