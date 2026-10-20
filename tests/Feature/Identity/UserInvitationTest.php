<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SetPassword;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('an administrator invite assigns one role and sends a set-password link', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->set('name', 'New Treasurer')
        ->set('email', 'new.treasurer@greenmeadows.test')
        ->set('role', RoleName::Treasurer->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'new.treasurer@greenmeadows.test')->firstOrFail();

    expect($user->roles)->toHaveCount(1)
        ->and($user->hasRole(RoleName::Treasurer))->toBeTrue();

    Notification::assertSentTo($user, SetPassword::class);
});

test('an invite requires a name', function () {
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->set('name', '')
        ->set('email', 'new.treasurer@greenmeadows.test')
        ->set('role', RoleName::Treasurer->value)
        ->call('createUser')
        ->assertHasErrors('name')
        ->assertSee('The name field is required.');
});

test('a deactivated user cannot sign in', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
