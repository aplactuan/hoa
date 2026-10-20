<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\AccessSeeder;

test('each role sees only its modules and hidden modules return 403', function (RoleName $role) {
    $this->seed(AccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole($role);

    $home = $this->actingAs($user)->get(route($user->homeRoute()));
    $home->assertOk();

    $navigation = config('access.navigation');
    expect($navigation)->toBeArray();

    foreach ($navigation as $group) {
        expect($group)->toBeArray();

        foreach ($group['items'] as $item) {
            $response = $this->actingAs($user)->get(route($item['route']));

            if ($user->hasPermission($item['permission'])) {
                $response->assertOk();
                $home->assertSee($item['label']);
            } else {
                $response->assertForbidden();
                $home->assertDontSee($item['label']);
            }
        }
    }
})->with(RoleName::cases());
