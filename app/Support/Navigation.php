<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * @return list<array{heading: string, items: list<array{label: string, route: string, icon: string, badge: int|null}>}>
     */
    public function groupsFor(User $user): array
    {
        $navigation = config('access.navigation');

        if (! is_array($navigation)) {
            return [];
        }

        $groups = [];

        foreach ($navigation as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = [];
            $entries = $group['items'] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $item) {
                if (! is_array($item) || ! is_string($item['permission'] ?? null) || ! $user->hasPermission($item['permission'])) {
                    continue;
                }

                $badge = null;

                if (($item['badge'] ?? null) === 'users') {
                    $count = User::query()->count();
                    $badge = $count > 0 ? $count : null;
                }

                $items[] = [
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'route' => is_string($item['route'] ?? null) ? $item['route'] : 'dashboard',
                    'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : 'home',
                    'badge' => $badge,
                ];
            }

            if ($items !== [] && is_string($group['heading'] ?? null)) {
                $groups[] = [
                    'heading' => $group['heading'],
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }
}
