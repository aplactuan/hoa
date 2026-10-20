<?php

namespace App\Enums;

enum DeviceKind: string
{
    case Pc = 'pc';
    case Tablet = 'tablet';

    public function label(): string
    {
        return match ($this) {
            self::Pc => 'PC',
            self::Tablet => 'Tablet',
        };
    }
}
