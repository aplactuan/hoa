<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\AccessSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed(AccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(RoleName::OfficeStaff);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
