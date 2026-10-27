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

/**
 * @property string $lot_area
 * @property-read Street|null $street
 * @property-read Owner|null $currentOwner
 * @property-read Resident|null $currentResident
 */
#[Fillable(['code', 'account_no', 'membership_no', 'block', 'lot', 'house_no', 'street_id', 'section_id', 'property_type_id', 'lot_area', 'status', 'notes', 'current_owner_id', 'current_resident_id', 'created_by', 'updated_by'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['lot_area' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Street, $this>
     */
    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<PropertyType, $this>
     */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'current_owner_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function currentResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'current_resident_id');
    }

    /**
     * @return HasMany<Owner, $this>
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<HouseStaff, $this>
     */
    public function houseStaff(): HasMany
    {
        return $this->hasMany(HouseStaff::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<DuesStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(DuesStatement::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<OfficeTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(OfficeTransaction::class);
    }

    /**
     * @return HasMany<ServiceCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(ServiceCase::class);
    }

    /**
     * @return HasMany<Violation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    /**
     * @return HasMany<Correspondence, $this>
     */
    public function correspondence(): HasMany
    {
        return $this->hasMany(Correspondence::class);
    }

    /**
     * @return HasMany<FacilityReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(FacilityReservation::class);
    }

    /**
     * @return HasMany<OwnerChangeRequest, $this>
     */
    public function ownerChangeRequests(): HasMany
    {
        return $this->hasMany(OwnerChangeRequest::class);
    }
}
