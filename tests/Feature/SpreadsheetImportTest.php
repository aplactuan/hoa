<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\Section;
use App\Models\Street;
use App\Models\User;
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
    file_put_contents($path, "block,lot,lot_area,house_no\n1,1,100,12\n1,2,120,13\n");

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

test('only administrators can open spreadsheet import', function () {
    $this->seed(AccessSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->actingAs($administrator)->get(route('imports.index'))->assertOk();
    $this->actingAs($guard)->get(route('imports.index'))->assertForbidden();
});
