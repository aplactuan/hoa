<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use App\Notifications\SetPassword;
use App\Services\PropertyRegistry;
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

test('an administrator can invite an owner without a 404', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);
    $property = Property::factory()->create();
    $owner = app(PropertyRegistry::class)->transferOwnership($property, [
        'name' => 'Olivia Owner',
        'ownership_type' => 'Sole Owner',
        'started_on' => now()->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->set('email', 'olivia.owner@greenmeadows.test')
        ->set('role', RoleName::Owner->value)
        ->set('ownerId', (string) $owner->id)
        ->call('createUser')
        ->assertHasNoErrors()
        ->assertOk();

    $user = User::query()->where('email', 'olivia.owner@greenmeadows.test')->firstOrFail();

    expect($user->hasRole(RoleName::Owner))->toBeTrue()
        ->and($owner->fresh()->user_id)->toBe($user->id);
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
