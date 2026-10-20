<?php

namespace App\Enums;

enum AuditAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Payment = 'payment';
    case Void = 'void';
    case Login = 'login';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::Payment => 'Payment',
            self::Void => 'Void',
            self::Login => 'Login',
            self::Export => 'Export',
        };
    }
}
