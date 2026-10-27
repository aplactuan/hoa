<?php

namespace App\Enums;

enum RoleName: string
{
    case Administrator = 'administrator';
    case Treasurer = 'treasurer';
    case OfficeStaff = 'office_staff';
    case SecurityGuard = 'security_guard';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Treasurer => 'Treasurer',
            self::OfficeStaff => 'Office Staff',
            self::SecurityGuard => 'Security Guard',
            self::Owner => 'Owner',
        };
    }
}
