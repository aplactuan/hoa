<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'account_no', 'membership_no', 'block', 'lot', 'house_no', 'street_id', 'section_id', 'property_type_id', 'lot_area', 'status', 'notes', 'current_owner_id', 'current_resident_id', 'created_by', 'updated_by'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['lot_area' => 'decimal:2'];
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'current_owner_id');
    }

    public function currentResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'current_resident_id');
    }

    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function houseStaff(): HasMany
    {
        return $this->hasMany(HouseStaff::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(DuesStatement::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OfficeTransaction::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ServiceCase::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    public function correspondence(): HasMany
    {
        return $this->hasMany(Correspondence::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class);
    }
}
