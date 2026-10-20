<?php

use App\Enums\RoleName;
use App\Models\Facility;
use App\Models\Property;
use App\Models\User;
use App\Services\OfficeService;
use Database\Seeders\AccessSeeder;
use Livewire\Livewire;

test('reservation alerts only administrators and recipients can mark their own alerts read', function () {
    $this->seed(AccessSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $office = User::factory()->create();
    $office->assignRole(RoleName::OfficeStaff);
    $facility = Facility::factory()->create();
    $property = Property::factory()->create();

    app(OfficeService::class)->bookFacility($property, $facility, now()->addDay()->toDateString(), '10:00', '12:00', 'Party', 20);

    $alert = $administrator->unreadNotifications()->sole();
    expect($alert->data['route'])->toBe('facilities.index')
        ->and($office->notifications()->count())->toBe(0);

    $this->actingAs($administrator)->get(route('notifications.index'))->assertOk()->assertSee('Reservation awaiting approval');
    Livewire::actingAs($office)->test('pages::notifications')->call('markRead', $alert->id)->assertNotFound();
    Livewire::actingAs($administrator)->test('pages::notifications')->call('markRead', $alert->id);
    expect($administrator->unreadNotifications()->count())->toBe(0);
});
