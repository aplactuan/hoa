<?php

use App\Enums\RoleName;
use App\Models\HouseStaff;
use App\Models\Property;
use App\Models\User;
use App\Services\BillingService;
use App\Services\GateCheckService;
use App\Services\PropertyRegistry;
use Database\Seeders\AccessSeeder;
use Livewire\Livewire;

test('gate check returns only allowed vehicle fields and a valid sticker verdict', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create(['block' => 2, 'lot' => 4]);
    $registry = app(PropertyRegistry::class);
    $vehicle = $registry->registerVehicle($property, [
        'plate_no' => 'ABC 123',
        'driver_name' => 'Maria Santos',
        'type' => 'Car',
        'color' => 'Blue',
    ]);
    $registry->issueSticker($vehicle, 'GM-2026-001', now()->year);
    app(BillingService::class)->createCharge($property, 'Private balance', '98765.43');
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $results = app(GateCheckService::class)->check('ABC123');
    expect($results[0]['verdict'])->toBe('VALID')
        ->and($results[0]['property'])->toContain('Block 2')
        ->and(json_encode($results))->not->toContain('98765.43');

    Livewire::actingAs($guard)->test('pages::gate-check')
        ->set('query', 'ABC123')
        ->call('check')
        ->assertSee('VALID')
        ->assertDontSee('98765.43');
});

test('expired staff passes are marked expired without exposing ID references', function () {
    $property = Property::factory()->create();
    HouseStaff::factory()->create([
        'property_id' => $property->id,
        'name' => 'Nora Helper',
        'pass_no' => 'PASS-0099',
        'pass_expires_on' => now()->subDay()->toDateString(),
        'id_reference' => 'SECRET-IDENTIFIER',
    ]);

    $results = app(GateCheckService::class)->check('PASS-0099');

    expect($results[0]['verdict'])->toBe('PASS EXP.')
        ->and(json_encode($results))->not->toContain('SECRET-IDENTIFIER');
});
