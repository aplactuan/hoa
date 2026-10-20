<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\AccessSeeder;

test('an idle session is signed out', function () {
    $this->seed(AccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(RoleName::Administrator);

    $lifetime = (int) config('session.lifetime');

    $this->actingAs($user)
        ->withSession(['last_seen_at' => now()->subMinutes($lifetime + 1)->getTimestamp()])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a session inside the idle window stays signed in', function () {
    $this->seed(AccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(RoleName::Administrator);

    $this->actingAs($user)
        ->withSession(['last_seen_at' => now()->subMinute()->getTimestamp()])
        ->get(route('dashboard'))
        ->assertOk();

    $this->assertAuthenticated();
});
