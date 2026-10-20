<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyRegistry;
use Database\Seeders\AccessSeeder;

test('search opens property records and hides owner details from guards', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create(['block' => 2, 'lot' => 4]);
    $registry = app(PropertyRegistry::class);
    $registry->transferOwnership($property, [
        'name' => 'Maria Santos',
        'ownership_type' => 'Sole Owner',
        'started_on' => now()->toDateString(),
    ]);
    $registry->registerVehicle($property, ['plate_no' => 'ABC 123', 'driver_name' => 'Maria Santos', 'type' => 'Car']);
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->actingAs($administrator)->get(route('search', ['q' => 'Maria']))
        ->assertOk()->assertSee('Owners')->assertSee('Maria Santos');
    $this->actingAs($guard)->get(route('search', ['q' => 'Maria']))
        ->assertOk()->assertDontSee('Owners')->assertSee('Vehicles');
    $this->actingAs($guard)->get(route('search', ['q' => 'ABC']))
        ->assertOk()->assertSee('ABC 123');
});
