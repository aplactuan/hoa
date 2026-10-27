<?php

use App\Enums\RoleName;
use App\Models\Owner;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\AccessSeeder;

test('each role sees only its modules and hidden modules return 403', function (RoleName $role) {
    $this->seed(AccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole($role);

    if ($role === RoleName::Owner) {
        Owner::factory()->create(['user_id' => $user->id]);
    }

    $home = $this->actingAs($user)->get(route($user->homeRoute()));
    $home->assertOk();

    $navigation = config('access.navigation');
    expect($navigation)->toBeArray();
    $expectedVisibleRoutes = [];

    foreach ($navigation as $group) {
        expect($group)->toBeArray();

        foreach ($group['items'] as $item) {
            $response = $this->actingAs($user)->get(route($item['route']));
            $allowedRoles = $item['roles'] ?? [];
            $hasAllowedRole = $allowedRoles === []
                || $user->roles->contains(fn ($assignedRole): bool => in_array($assignedRole->name, $allowedRoles, true));
            $shouldSeeItem = $user->hasPermission($item['permission']) && $hasAllowedRole;

            if ($shouldSeeItem) {
                $response->assertOk();
                $expectedVisibleRoutes[] = $item['route'];
            } else {
                $response->assertForbidden();
            }
        }
    }

    $visibleRoutes = collect(app(Navigation::class)->groupsFor($user))
        ->flatMap(fn (array $group) => $group['items'])
        ->pluck('route')
        ->all();

    expect($visibleRoutes)
        ->toBe($expectedVisibleRoutes)
        ->toContain($user->homeRoute());
})->with(RoleName::cases());
