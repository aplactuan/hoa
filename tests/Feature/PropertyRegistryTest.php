<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\HouseStaff;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Resident;
use App\Models\Section;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\SetPassword;
use App\Services\BillingService;
use App\Services\PropertyRegistry;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('office staff can create a property with linked identifiers and an audit entry', function () {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($staff)->test('pages::properties')
        ->set('block', '2')
        ->set('lot', '4')
        ->set('lotArea', '125.50')
        ->call('save')
        ->assertHasNoErrors();

    $property = Property::query()->sole();
    expect($property->code)->toBe('B2L4')
        ->and($property->account_no)->toBe('ACC-0001')
        ->and($property->membership_no)->toBe('MEM-0001')
        ->and($property->lot_area)->toBe('125.50');
    expect(AuditLog::query()->where('auditable_type', Property::class)->where('auditable_id', $property->id)->exists())->toBeTrue();
});

test('editing a property location updates its code without changing its account identifiers', function () {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $property = Property::factory()->create(['block' => 1, 'lot' => 2]);

    Livewire::actingAs($staff)->test('pages::properties')
        ->call('edit', $property->id)
        ->set('block', '3')
        ->set('lot', '4')
        ->call('save')
        ->assertHasNoErrors();

    expect($property->fresh()->code)->toBe('B3L4')
        ->and($property->fresh()->account_no)->toBe($property->account_no)
        ->and($property->fresh()->membership_no)->toBe($property->membership_no);
});

test('a duplicate block and lot is rejected', function () {
    $this->seed(AccessSeeder::class);
    Property::factory()->create(['block' => 2, 'lot' => 4]);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($staff)->test('pages::properties')
        ->set('block', '2')
        ->set('lot', '4')
        ->set('lotArea', '125.50')
        ->call('save')
        ->assertHasErrors('lot');

    expect(Property::query()->count())->toBe(1);
});

test('adding a property defaults the section to phase 1', function () {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $phaseOne = Section::factory()->create(['name' => 'Phase 1']);
    Section::factory()->create(['name' => 'Phase 2']);

    Livewire::actingAs($staff)->test('pages::properties')
        ->call('createRecord')
        ->assertSet('sectionId', (string) $phaseOne->id)
        ->set('block', '2')
        ->set('lot', '4')
        ->set('lotArea', '125.50')
        ->call('save')
        ->assertHasNoErrors();

    expect(Property::query()->sole()->section_id)->toBe($phaseOne->id);
});

test('a treasurer cannot create a property through the component action', function () {
    $this->seed(AccessSeeder::class);
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);

    Livewire::actingAs($treasurer)->test('pages::properties')->call('createRecord')->assertForbidden();

    expect(Property::query()->count())->toBe(0);
});

test('office staff can open a blank add property form', function () {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($staff)->test('pages::properties')
        ->set('block', '9')
        ->set('lot', '12')
        ->call('createRecord')
        ->assertSeeHtml('wire:model.self="showModal"')
        ->assertSeeHtml('disable-click-outside')
        ->assertSet('showModal', true)
        ->assertSet('editingId', null)
        ->assertSet('block', '')
        ->assertSet('lot', '')
        ->assertSet('propertyStatus', 'Vacant');
});

test('property status options follow the selected type', function (string $typeName, array $expectedOptions, string $expectedStatus) {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $type = PropertyType::factory()->create(['name' => $typeName]);

    $form = Livewire::actingAs($staff)->test('pages::properties')
        ->call('createRecord')
        ->set('propertyTypeId', (string) $type->id)
        ->assertSet('showModal', true)
        ->assertSet('propertyStatus', $expectedStatus);

    preg_match('/<select[^>]*wire:model="propertyStatus"[^>]*>(.*?)<\/select>/s', $form->html(), $statusSelect);
    preg_match_all('/<option value="([^"]+)">/', $statusSelect[1], $options);

    expect($options[1])->toBe($expectedOptions);
})->with([
    'lot' => ['Lot', ['Vacant', 'Sta Lucia Owned', 'For Resale'], 'Vacant'],
    'house' => ['House', ['Underconstruction', 'Occupied'], 'Underconstruction'],
    'other type' => ['Townhouse', ['Vacant', 'Under Construction', 'For Sale'], 'Vacant'],
]);

test('property save accepts a status for its type and rejects one from another type', function () {
    $this->seed(AccessSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $type = PropertyType::factory()->create(['name' => 'Lot']);

    $form = Livewire::actingAs($staff)->test('pages::properties')
        ->call('createRecord')
        ->set('block', '2')
        ->set('lot', '4')
        ->set('lotArea', '125.50')
        ->set('propertyTypeId', (string) $type->id)
        ->set('propertyStatus', 'Occupied')
        ->call('save')
        ->assertHasErrors(['propertyStatus' => 'in']);

    expect(Property::query()->count())->toBe(0);

    $form->set('propertyStatus', 'For Resale')
        ->call('save')
        ->assertHasNoErrors();

    expect(Property::query()->sole()->status)->toBe('For Resale');
});

test('ownership transfer preserves former ownership and changes the current owner atomically', function () {
    $property = Property::factory()->create();
    $registry = app(PropertyRegistry::class);
    $first = $registry->transferOwnership($property, [
        'name' => 'First Owner',
        'ownership_type' => 'Sole Owner',
        'started_on' => '2025-01-01',
    ]);

    $second = $registry->transferOwnership($property, [
        'name' => 'Second Owner',
        'ownership_type' => 'Sole Owner',
        'started_on' => '2026-02-01',
    ]);

    expect($first->fresh()->status)->toBe('Former')
        ->and($first->fresh()->ended_on->toDateString())->toBe('2026-02-01')
        ->and($second->status)->toBe('Current')
        ->and($property->fresh()->current_owner_id)->toBe($second->id)
        ->and(Owner::query()->where('property_id', $property->id)->count())->toBe(2);
});

test('recording an owner requires an email', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('ownerName', 'Maria Santos')
        ->set('ownerStartedOn', '2026-01-01')
        ->call('saveOwner')
        ->assertHasErrors(['ownerEmail' => 'required']);

    expect($property->fresh()->current_owner_id)->toBeNull()
        ->and(Owner::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

test('recording a new owner creates an account and sends a set-password email', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('ownerName', 'Maria Santos')
        ->set('ownerEmail', ' Maria.Santos@Example.Test ')
        ->set('ownerStartedOn', '2026-01-01')
        ->call('saveOwner')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'maria.santos@example.test')->firstOrFail();
    $owner = $property->fresh()->currentOwner;

    expect($user->hasRole(RoleName::Owner))->toBeTrue()
        ->and($owner->user_id)->toBe($user->id)
        ->and($owner->email)->toBe($user->email)
        ->and($owner->is_registered)->toBeTrue()
        ->and(User::query()->count())->toBe(2);

    Notification::assertSentTo($user, SetPassword::class);
});

test('transferring ownership links an existing email without replacing its role or sending an invitation', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $firstOwner = app(PropertyRegistry::class)->transferOwnership($property, [
        'name' => 'First Owner',
        'ownership_type' => 'Sole Owner',
        'started_on' => '2025-01-01',
    ]);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $existingUser = User::factory()->create(['email' => 'Maria.Santos@Example.Test']);
    $existingUser->assignRole(RoleName::Administrator);

    Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('ownerName', 'Maria Santos')
        ->set('ownerEmail', 'maria.santos@example.test')
        ->set('ownerStartedOn', '2026-01-01')
        ->call('saveOwner')
        ->assertHasNoErrors();

    $currentOwner = $property->fresh()->currentOwner;

    expect($firstOwner->fresh()->status)->toBe('Former')
        ->and($currentOwner->name)->toBe('Maria Santos')
        ->and($currentOwner->user_id)->toBe($existingUser->id)
        ->and($existingUser->fresh()->hasRole(RoleName::Administrator))->toBeTrue()
        ->and($existingUser->fresh()->hasRole(RoleName::Owner))->toBeFalse()
        ->and(User::query()->count())->toBe(2);

    Notification::assertNothingSent();
});

test('moving out clears current occupancy', function () {
    $property = Property::factory()->create();
    $registry = app(PropertyRegistry::class);
    $resident = $registry->moveIn($property, [
        'name' => 'Mara Resident',
        'occupancy_type' => 'Tenant',
        'moved_in_on' => '2026-01-01',
    ]);

    $registry->moveOut($resident, '2026-03-01');

    expect($resident->fresh()->status)->toBe('Moved Out')
        ->and($property->fresh()->current_resident_id)->toBeNull()
        ->and($property->fresh()->status)->toBe('Vacant')
        ->and(Resident::query()->count())->toBe(1);
});

test('property profile records a transfer and masks ownership and money from the guard', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('ownerName', 'Maria Santos')
        ->set('ownerEmail', 'maria.santos@example.test')
        ->set('ownerType', 'Sole Owner')
        ->set('ownerStartedOn', '2026-01-01')
        ->call('saveOwner')
        ->assertHasNoErrors();

    expect($property->fresh()->currentOwner?->name)->toBe('Maria Santos');
    $property->fresh()->currentOwner->update(['tct_no' => 'SECRET-TITLE']);
    app(BillingService::class)->createCharge($property, 'Private charge', '98765.43');

    $this->actingAs($guard)->get(route('properties.show', $property))
        ->assertOk()
        ->assertDontSee('Maria Santos')
        ->assertDontSee('SECRET-TITLE')
        ->assertDontSee('98765.43')
        ->assertDontSee('₱');
    Livewire::actingAs($guard)->test('pages::property-profile', ['property' => $property])
        ->set('tab', 'Ownership')
        ->assertDontSee('Maria Santos');
});

test('sticker issuance creates one annual record and its fee', function () {
    $property = Property::factory()->create();
    $vehicle = Vehicle::factory()->create(['property_id' => $property->id]);
    $registry = app(PropertyRegistry::class);

    $sticker = $registry->issueSticker($vehicle, 'GM-2026-001', 2026);

    expect($sticker->fresh()->charge->amount)->toBe('600.00')
        ->and($sticker->fresh()->charge->source_id)->toBe($sticker->id)
        ->and($property->charges()->count())->toBe(1);

    expect(fn () => $registry->issueSticker($vehicle, 'GM-2026-002', 2026))
        ->toThrow(ValidationException::class);
    expect($property->charges()->count())->toBe(1);
});

test('vehicle registration requires owner and vehicle details', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    $form = Livewire::actingAs($staff)->test('pages::property-profile', ['property' => $property])
        ->set('plateNo', 'ABC 123')
        ->call('saveVehicle')
        ->assertHasErrors(['vehicleOwnerName' => 'required', 'vehicleMake' => 'required', 'vehicleModel' => 'required', 'vehicleColor' => 'required', 'vehicleCrNumber' => 'required']);

    expect(Vehicle::query()->count())->toBe(0);

    $form->set('vehicleOwnerName', 'Maria Santos')
        ->set('vehicleMake', 'Toyota')
        ->set('vehicleModel', 'Vios')
        ->set('vehicleColor', 'Blue')
        ->set('vehicleCrNumber', 'CR-12345')
        ->call('saveVehicle')
        ->assertHasNoErrors();

    $vehicle = Vehicle::query()->sole();

    expect($vehicle->driver_name)->toBe('Maria Santos')
        ->and($vehicle->make)->toBe('Toyota')
        ->and($vehicle->model)->toBe('Vios')
        ->and($vehicle->color)->toBe('Blue')
        ->and($vehicle->cr_number)->toBe('CR-12345');
});

test('office staff can renew an active house staff pass', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $staff = HouseStaff::factory()->create(['property_id' => $property->id, 'status' => 'Active', 'pass_expires_on' => now()->addDay()]);
    $office = User::factory()->create();
    $office->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($office)->test('pages::property-profile', ['property' => $property])
        ->set('renewStaffId', (string) $staff->id)
        ->set('renewPassExpiresOn', now()->addYear()->toDateString())
        ->call('renewStaffPass')
        ->assertHasNoErrors();

    expect($staff->fresh()->pass_expires_on->toDateString())->toBe(now()->addYear()->toDateString());
});
