<?php

use App\Actions\InviteOwnerUser;
use App\Actions\LinkOwnerUser;
use App\Enums\OwnerChangeStatus;
use App\Enums\OwnerChangeType;
use App\Enums\RoleName;
use App\Models\DuesStatement;
use App\Models\Owner;
use App\Models\OwnerChangeRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Notifications\OfficeAttention;
use App\Notifications\SetPassword;
use App\Services\OwnerChangeRequestService;
use App\Services\PropertyRegistry;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
});

function currentOwner(Property $property, array $attributes = []): Owner
{
    return app(PropertyRegistry::class)->transferOwnership($property, array_merge([
        'name' => 'Maria Santos',
        'ownership_type' => 'Sole Owner',
        'started_on' => now()->toDateString(),
    ], $attributes));
}

test('an administrator can invite an owner and link additional current properties', function () {
    Notification::fake();
    $firstOwner = currentOwner(Property::factory()->create());
    $secondOwner = currentOwner(Property::factory()->create(), ['name' => 'Maria Santos']);

    $user = app(InviteOwnerUser::class)->handle($firstOwner, 'maria@example.test');
    app(LinkOwnerUser::class)->handle($user, $secondOwner);

    expect($user->hasRole(RoleName::Owner))->toBeTrue()
        ->and($user->homeRoute())->toBe('owner.dashboard')
        ->and($user->currentOwnedProperties()->count())->toBe(2)
        ->and($firstOwner->fresh()->user_id)->toBe($user->id)
        ->and($secondOwner->fresh()->user_id)->toBe($user->id);

    Notification::assertSentTo($user, SetPassword::class);
});

test('an owner can only select and view current linked properties', function () {
    $ownedProperty = Property::factory()->create();
    $foreignProperty = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    app(LinkOwnerUser::class)->handle($user, currentOwner($ownedProperty));
    currentOwner($foreignProperty);

    $this->actingAs($user)->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee($ownedProperty->code)
        ->assertDontSee($foreignProperty->code);

    Livewire::actingAs($user)
        ->test('pages::owner.dashboard')
        ->call('selectProperty', $foreignProperty->id)
        ->assertStatus(404);
});

test('owner statements receipts and private documents are property scoped', function () {
    Storage::fake('local');
    $ownedProperty = Property::factory()->create();
    $foreignProperty = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    $ownedOwner = app(LinkOwnerUser::class)->handle($user, currentOwner($ownedProperty));
    $foreignOwner = currentOwner($foreignProperty);

    $ownedStatement = DuesStatement::factory()->for($ownedProperty)->create();
    $foreignStatement = DuesStatement::factory()->for($foreignProperty)->create();
    $ownedPayment = Payment::factory()->for($ownedProperty)->create();
    $foreignPayment = Payment::factory()->for($foreignProperty)->create();
    Storage::disk('local')->put('documents/owned.pdf', 'owned');
    Storage::disk('local')->put('documents/foreign.pdf', 'foreign');
    $ownedAttachment = $ownedOwner->attachments()->create([
        'disk' => 'local', 'path' => 'documents/owned.pdf', 'original_name' => 'owned.pdf', 'size' => 5,
    ]);
    $foreignAttachment = $foreignOwner->attachments()->create([
        'disk' => 'local', 'path' => 'documents/foreign.pdf', 'original_name' => 'foreign.pdf', 'size' => 7,
    ]);

    $this->actingAs($user)->get(route('statements.print', $ownedStatement))->assertOk();
    $this->actingAs($user)->get(route('receipts.print', $ownedPayment))->assertOk();
    $this->actingAs($user)->get(route('attachments.download', $ownedAttachment))->assertOk();
    $this->actingAs($user)->get(route('statements.print', $foreignStatement))->assertForbidden();
    $this->actingAs($user)->get(route('receipts.print', $foreignPayment))->assertForbidden();
    $this->actingAs($user)->get(route('attachments.download', $foreignAttachment))->assertNotFound();
});

test('owner changes require staff approval before official records are updated', function () {
    Notification::fake();
    $property = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    $owner = app(LinkOwnerUser::class)->handle($user, currentOwner($property, ['phone' => '09170000000']));
    $reviewer = User::factory()->create();
    $reviewer->assignRole(RoleName::OfficeStaff);

    $changeRequest = app(OwnerChangeRequestService::class)->submit(
        $user,
        $property,
        OwnerChangeType::OwnerContact,
        ['phone' => '09179999999'],
        $owner,
    );

    expect($owner->fresh()->phone)->toBe('09170000000')
        ->and($changeRequest->status)->toBe(OwnerChangeStatus::Pending);

    app(OwnerChangeRequestService::class)->approve($changeRequest, $reviewer, 'Verified by the office.');

    $changeRequest = $changeRequest->fresh();

    expect($owner->fresh()->phone)->toBe('09179999999')
        ->and($changeRequest->status)->toBe(OwnerChangeStatus::Approved)
        ->and($changeRequest->reviewer->is($reviewer))->toBeTrue();

    Notification::assertSentTo($user, OfficeAttention::class);
});

test('cancelled and declined owner changes do not update official records', function () {
    Notification::fake();
    $property = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    $owner = app(LinkOwnerUser::class)->handle($user, currentOwner($property, ['phone' => '09170000000']));
    $reviewer = User::factory()->create();
    $reviewer->assignRole(RoleName::Administrator);
    $changes = app(OwnerChangeRequestService::class);

    $cancelled = $changes->submit($user, $property, OwnerChangeType::OwnerContact, ['phone' => '111'], $owner);
    $changes->cancel($cancelled, $user);
    $declined = $changes->submit($user, $property, OwnerChangeType::OwnerContact, ['phone' => '222'], $owner);
    $changes->decline($declined, $reviewer, 'Supporting document required.');

    expect($owner->fresh()->phone)->toBe('09170000000')
        ->and($cancelled->fresh()->status)->toBe(OwnerChangeStatus::Cancelled)
        ->and($declined->fresh()->status)->toBe(OwnerChangeStatus::Declined);
});

test('an owner cannot add a family member until a current resident exists', function () {
    $property = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    app(LinkOwnerUser::class)->handle($user, currentOwner($property));

    Livewire::actingAs($user)
        ->test('pages::owner.property')
        ->assertSee('Submit a resident first')
        ->set('familyName', 'Ana Santos')
        ->set('familyRelationship', 'Daughter')
        ->call('submitFamily')
        ->assertHasErrors(['familyName']);
});

test('submitting a resident does not validate house staff fields', function () {
    Notification::fake();
    $property = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    app(LinkOwnerUser::class)->handle($user, currentOwner($property));

    Livewire::actingAs($user)
        ->test('pages::owner.property')
        ->set('residentName', 'Adrian Lactuan')
        ->set('residentPhone', '09363538792')
        ->set('residentEmail', 'aplactuan@gmail.com')
        ->call('submitResident')
        ->assertHasNoErrors()
        ->assertDontSee('The record type field is required')
        ->assertDontSee('The staff id type field is required');

    $changeRequest = OwnerChangeRequest::query()->where('property_id', $property->id)->sole();

    expect($changeRequest->type)->toBe(OwnerChangeType::ResidentMoveIn)
        ->and($changeRequest->payload['name'])->toBe('Adrian Lactuan')
        ->and($changeRequest->payload['occupancy_type'])->toBe('Owner-Occupied');
});

test('a former owner immediately loses portal and financial document access', function () {
    $property = Property::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);
    app(LinkOwnerUser::class)->handle($user, currentOwner($property));
    $statement = DuesStatement::factory()->for($property)->create();

    app(PropertyRegistry::class)->transferOwnership($property, [
        'name' => 'New Owner',
        'ownership_type' => 'Sole Owner',
        'started_on' => now()->addDay()->toDateString(),
    ]);

    $this->actingAs($user)->get(route('owner.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('statements.print', $statement))->assertForbidden();
    $this->actingAs($user)->get(route('receipts.index'))->assertForbidden();
});
