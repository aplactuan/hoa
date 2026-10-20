<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;

class Money
{
    public static function format(float|int|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $viewer = auth()->user();

        if (! $viewer instanceof User || ! $viewer->hasPermission(Permission::FinancialView)) {
            return null;
        }

        return '₱'.number_format((float) $amount, 2);
    }
}
