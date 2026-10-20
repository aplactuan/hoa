<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\OfficeAttention;
use Illuminate\Support\Facades\Notification;

class OfficeAlerts
{
    /** @param list<RoleName> $roles */
    public function send(array $roles, string $title, string $message, string $routeName): void
    {
        $names = array_map(fn (RoleName $role): string => $role->value, $roles);
        $recipients = User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $names))->get();

        Notification::send($recipients, new OfficeAttention($title, $message, $routeName));
    }
}
