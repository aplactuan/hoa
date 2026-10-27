<?php

namespace App\Enums;

enum OwnerChangeType: string
{
    case OwnerContact = 'owner_contact';
    case ResidentMoveIn = 'resident_move_in';
    case ResidentUpdate = 'resident_update';
    case ResidentMoveOut = 'resident_move_out';
    case FamilyAdd = 'family_add';
    case FamilyUpdate = 'family_update';
    case FamilyRemove = 'family_remove';
    case HouseStaffAdd = 'house_staff_add';
    case HouseStaffUpdate = 'house_staff_update';
    case HouseStaffDeactivate = 'house_staff_deactivate';
    case VehicleAdd = 'vehicle_add';
    case VehicleUpdate = 'vehicle_update';
    case VehicleDeactivate = 'vehicle_deactivate';

    public function label(): string
    {
        return match ($this) {
            self::OwnerContact => 'Update owner contact details',
            self::ResidentMoveIn => 'Move in resident',
            self::ResidentUpdate => 'Update resident',
            self::ResidentMoveOut => 'Move out resident',
            self::FamilyAdd => 'Add family member',
            self::FamilyUpdate => 'Update family member',
            self::FamilyRemove => 'Remove family member',
            self::HouseStaffAdd => 'Add house staff',
            self::HouseStaffUpdate => 'Update house staff',
            self::HouseStaffDeactivate => 'Deactivate house staff',
            self::VehicleAdd => 'Register vehicle',
            self::VehicleUpdate => 'Update vehicle',
            self::VehicleDeactivate => 'Deactivate vehicle',
        };
    }
}
