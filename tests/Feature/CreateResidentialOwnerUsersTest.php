<?php

use App\Enums\RoleName;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('creates one ready to use owner account for residential properties only', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $commercial = PropertyCategory::factory()->create(['name' => 'Commercial']);
    $first = Property::factory()->create(['code' => 'B75L4', 'block' => 75, 'lot' => 4, 'property_category_id' => $residential->id]);
    $second = Property::factory()->create(['code' => 'B75L6', 'block' => 75, 'lot' => 6, 'property_category_id' => $residential->id]);
    $other = Property::factory()->create(['property_category_id' => $commercial->id]);
    $corporate = Property::factory()->create(['property_category_id' => $commercial->id]);
    $firstOwner = Owner::factory()->for($first)->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $secondOwner = Owner::factory()->for($second)->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $otherOwner = Owner::factory()->for($other)->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $corporateOwner = Owner::factory()->for($corporate)->create(['first_name' => 'Sta Lucia', 'last_name' => null, 'name' => 'Sta Lucia', 'ownership_type' => 'Corporate']);

    $this->artisan('owners:create-residential-users', ['--password' => 'Gr33n_M34d0w5!'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'bria_sant@agweb.io')->sole();
    expect($user->name)->toBe('Brian Santos')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Gr33n_M34d0w5!', $user->password))->toBeTrue()
        ->and($user->hasRole(RoleName::Owner))->toBeTrue()
        ->and($user->currentOwnedProperties()->pluck('code')->sort()->values()->all())->toBe(['B75L4', 'B75L6']);
    expect($firstOwner->fresh()->user_id)->toBe($user->id)
        ->and($secondOwner->fresh()->user_id)->toBe($user->id)
        ->and($otherOwner->fresh()->user_id)->toBeNull()
        ->and($corporateOwner->fresh()->user_id)->toBeNull();
    expect(User::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('dry run makes no changes and a second run keeps existing links', function () {
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $property = Property::factory()->create(['property_category_id' => $residential->id]);
    $owner = Owner::factory()->for($property)->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);

    $this->artisan('owners:create-residential-users', ['--dry-run' => true])->assertSuccessful();
    expect(User::query()->count())->toBe(0)
        ->and($owner->fresh()->user_id)->toBeNull();

    $this->artisan('owners:create-residential-users', ['--password' => 'Gr33n_M34d0w5!'])->assertSuccessful();
    $user = User::query()->sole();
    $originalPassword = $user->password;
    $this->artisan('owners:create-residential-users', ['--password' => 'another-password'])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and($owner->fresh()->user_id)->toBe($user->id)
        ->and($user->fresh()->password)->toBe($originalPassword);
});

test('skips a generated email collision and an owner already linked elsewhere', function () {
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $first = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $collision = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Brianna', 'last_name' => 'Santoro', 'name' => 'Brianna Santoro']);
    $alreadyLinked = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Maria', 'last_name' => 'Reyes', 'name' => 'Maria Reyes']);
    $existingUser = User::factory()->create();
    $alreadyLinked->update(['user_id' => $existingUser->id]);

    $this->artisan('owners:create-residential-users', ['--password' => 'Gr33n_M34d0w5!'])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and($first->fresh()->user_id)->toBeNull()
        ->and($collision->fresh()->user_id)->toBeNull()
        ->and($alreadyLinked->fresh()->user_id)->toBe($existingUser->id);
});

test('adds another residential property to an existing matching owner account', function () {
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $first = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $second = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);
    $user = User::factory()->create(['name' => 'Brian Santos', 'email' => 'bria_sant@agweb.io']);
    $user->assignRole(RoleName::Owner);
    $first->update(['user_id' => $user->id]);

    $this->artisan('owners:create-residential-users', ['--password' => 'Gr33n_M34d0w5!'])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and($second->fresh()->user_id)->toBe($user->id)
        ->and($user->currentOwnedProperties()->count())->toBe(2);
});

test('skips residential owners without a last name', function () {
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $owner = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Sta Lucia', 'last_name' => null, 'name' => 'Sta Lucia']);

    $this->artisan('owners:create-residential-users', ['--password' => 'Gr33n_M34d0w5!'])->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and($owner->fresh()->user_id)->toBeNull();
});

test('requires a password before creating accounts', function () {
    $this->seed(AccessSeeder::class);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $owner = Owner::factory()->for(Property::factory()->create(['property_category_id' => $residential->id]))
        ->create(['first_name' => 'Brian', 'last_name' => 'Santos', 'name' => 'Brian Santos']);

    $this->artisan('owners:create-residential-users')->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and($owner->fresh()->user_id)->toBeNull();
});
