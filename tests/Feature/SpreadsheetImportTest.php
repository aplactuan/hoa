<?php

use App\Enums\RoleName;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyType;
use App\Models\Section;
use App\Models\Street;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AccountService;
use App\Services\SpreadsheetImport;
use Database\Seeders\AccessSeeder;
use Illuminate\Validation\ValidationException;

test('street import creates missing phases and reuses existing phases', function () {
    $existingPhase = Section::factory()->create(['name' => 'Phase 1']);
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "street_name,phase\nMeadow Lane,Phase 1\nCreek Road,Phase 2\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Streets and Phases', $path, 'streets.csv', null);
        $service->commit($batch, $path, null);

        expect($batch->fresh()->imported_count)->toBe(2)
            ->and(Section::query()->where('name', 'Phase 1')->count())->toBe(1)
            ->and(Section::query()->where('name', 'Phase 2')->value('is_active'))->toBeTrue();
        $this->assertDatabaseHas('streets', [
            'name' => 'Meadow Lane',
            'section_id' => $existingPhase->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('streets', [
            'name' => 'Creek Road',
            'section_id' => Section::query()->where('name', 'Phase 2')->value('id'),
            'is_active' => true,
        ]);
    } finally {
        unlink($path);
    }
});

test('street CSV preview reports an existing street in the same phase', function () {
    $phase = Section::factory()->create(['name' => 'Phase 1']);
    Street::factory()->for($phase)->create(['name' => 'Meadow Lane']);
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "street_name,phase\nMeadow Lane,Phase 1\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Streets and Phases', $path, 'streets.csv', null);

        expect($batch->row_count)->toBe(0)
            ->and($batch->error_count)->toBe(1)
            ->and($batch->errors()->sole()->row_number)->toBe(2);
        expect(fn () => $service->commit($batch, $path, null))->toThrow(ValidationException::class);
        expect(Street::query()->count())->toBe(1);
    } finally {
        unlink($path);
    }
});

test('property CSV preview reports duplicates and prevents import', function () {
    Property::factory()->create(['block' => 1, 'lot' => 1]);
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "block,lot,lot_area,house_no,street_name,status,property_type,property_category,property_address,notes\n1,1,100,12,,,,,,\n1,2,120,13,,,,,,\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Properties', $path, 'properties.csv', null);

        expect($batch->row_count)->toBe(1)
            ->and($batch->error_count)->toBe(1)
            ->and($batch->errors()->sole()->row_number)->toBe(2);
        expect(fn () => $service->commit($batch, $path, null))->toThrow(ValidationException::class);
        expect(Property::query()->count())->toBe(1);
    } finally {
        unlink($path);
    }
});

test('property import saves the extra columns and creates a missing property type', function () {
    $lot = PropertyType::factory()->create(['name' => 'Lot']);
    $creekRoad = Street::factory()->create(['name' => 'Creek Road']);
    $residential = PropertyCategory::factory()->create(['name' => 'Residential']);
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "block,lot,lot_area,house_no,street_name,status,property_type,property_category,property_address,notes\n2,4,125.50,12,Creek Road,Owned,Lot,Residential,123 Creek Road,Corner lot\n3,5,80,,New Lane,Vacant,Commercial,Mixed Use,,\n4,6,90,,,,,,,\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Properties', $path, 'properties.csv', null);
        expect($batch->error_count)->toBe(0);
        $service->commit($batch, $path, null);

        $owned = Property::query()->where('block', 2)->where('lot', 4)->sole();
        $commercialType = PropertyType::query()->where('name', 'Commercial')->sole();
        $blank = Property::query()->where('block', 4)->where('lot', 6)->sole();

        $newLane = Street::query()->where('name', 'New Lane')->sole();
        $mixedUse = PropertyCategory::query()->where('name', 'Mixed Use')->sole();

        expect($owned->property_type_id)->toBe($lot->id)
            ->and($owned->street_id)->toBe($creekRoad->id)
            ->and($owned->property_category_id)->toBe($residential->id)
            ->and($owned->status)->toBe('Owned')
            ->and($owned->property_address)->toBe('123 Creek Road')
            ->and($owned->notes)->toBe('Corner lot')
            ->and(PropertyType::query()->where('name', 'Lot')->count())->toBe(1)
            ->and(Street::query()->where('name', 'Creek Road')->count())->toBe(1)
            ->and(PropertyCategory::query()->where('name', 'Residential')->count())->toBe(1)
            ->and($commercialType->is_active)->toBeTrue()
            ->and($newLane->is_active)->toBeTrue()
            ->and($newLane->section_id)->toBeNull()
            ->and($mixedUse->is_active)->toBeTrue()
            ->and(Property::query()->where('block', 3)->where('lot', 5)->sole()->property_type_id)->toBe($commercialType->id)
            ->and(Property::query()->where('block', 3)->where('lot', 5)->sole()->street_id)->toBe($newLane->id)
            ->and(Property::query()->where('block', 3)->where('lot', 5)->sole()->property_category_id)->toBe($mixedUse->id)
            ->and($blank->status)->toBe('Vacant')
            ->and($blank->property_type_id)->toBeNull()
            ->and($blank->street_id)->toBeNull()
            ->and($blank->property_category_id)->toBeNull()
            ->and($blank->property_address)->toBeNull()
            ->and($blank->notes)->toBeNull();
    } finally {
        unlink($path);
    }
});

test('property import rejects a status that does not match the property type', function () {
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "block,lot,lot_area,house_no,street_name,status,property_type,property_category,property_address,notes\n1,1,100,,,Owned,House,Villa,,\n");

    try {
        $batch = app(SpreadsheetImport::class)->preview('Properties', $path, 'properties.csv', null);

        expect($batch->error_count)->toBe(1)
            ->and($batch->row_count)->toBe(0)
            ->and(Property::query()->count())->toBe(0)
            ->and(PropertyType::query()->where('name', 'House')->count())->toBe(0)
            ->and(PropertyCategory::query()->where('name', 'Villa')->count())->toBe(0);
    } finally {
        unlink($path);
    }
});

test('opening balances import atomically and reconciles the account total', function () {
    $first = Property::factory()->create();
    $second = Property::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "account_no,amount,as_of\n{$first->account_no},125.50,2026-09-30\n{$second->account_no},250.00,2026-09-30\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Opening Balances', $path, 'balances.csv', null);
        expect($batch->error_count)->toBe(0);
        $service->commit($batch, $path, null);

        expect($batch->fresh()->imported_count)->toBe(2)
            ->and($batch->fresh()->reconciled_amount)->toBe('375.50')
            ->and(app(AccountService::class)->summary($first)['outstanding_cents'])->toBe(12550);
        expect(fn () => $service->commit($batch->fresh(), $path, null))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
    }
});

test('vehicle import requires and saves owner and registration details', function () {
    $property = Property::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "property_code,owner_name,type,plate_no,make,model,color,cr_number\n{$property->code},Maria Santos,Car,ABC 123,Toyota,Vios,Blue,\n");

    try {
        $service = app(SpreadsheetImport::class);
        $invalidBatch = $service->preview('Vehicles', $path, 'vehicles.csv', null);

        expect($invalidBatch->error_count)->toBe(1)
            ->and(Vehicle::query()->count())->toBe(0);

        file_put_contents($path, "property_code,owner_name,type,plate_no,make,model,color,cr_number\n{$property->code},Maria Santos,Car,ABC 123,Toyota,Vios,Blue,CR-12345\n");
        $batch = $service->preview('Vehicles', $path, 'vehicles.csv', null);
        $service->commit($batch, $path, null);

        expect(Vehicle::query()->sole()->cr_number)->toBe('CR-12345')
            ->and(Vehicle::query()->sole()->driver_name)->toBe('Maria Santos');
    } finally {
        unlink($path);
    }
});

test('owner import saves first and last name and allows a blank last name', function () {
    $withLastName = Property::factory()->create();
    $withoutLastName = Property::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'hoa-import-');
    file_put_contents($path, "property_code,first_name,last_name,ownership_type,started_on,phone,email,mailing_address\n{$withLastName->code},Maria,Santos,Sole Owner,2026-01-01,,,123 Creek Road\n{$withoutLastName->code},Juan,,Sole Owner,2026-02-01,,,\n");

    try {
        $service = app(SpreadsheetImport::class);
        $batch = $service->preview('Owners', $path, 'owners.csv', null);
        expect($batch->error_count)->toBe(0);
        $service->commit($batch, $path, null);

        $maria = Owner::query()->where('property_id', $withLastName->id)->sole();
        $juan = Owner::query()->where('property_id', $withoutLastName->id)->sole();

        expect($maria->first_name)->toBe('Maria')
            ->and($maria->last_name)->toBe('Santos')
            ->and($maria->name)->toBe('Maria Santos')
            ->and($maria->mailing_address)->toBe('123 Creek Road')
            ->and($juan->first_name)->toBe('Juan')
            ->and($juan->last_name)->toBeNull()
            ->and($juan->name)->toBe('Juan')
            ->and($juan->mailing_address)->toBeNull();
    } finally {
        unlink($path);
    }
});

test('only administrators can open spreadsheet import', function () {
    $this->seed(AccessSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->actingAs($administrator)->get(route('imports.index'))
        ->assertOk()
        ->assertSee('block,lot,lot_area,house_no,street_name,status,property_type,property_category,property_address,notes', false)
        ->assertSee('property_code,first_name,last_name,ownership_type,started_on,phone,email,mailing_address', false);
    $this->actingAs($guard)->get(route('imports.index'))->assertForbidden();
});
