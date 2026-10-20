<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Permission;
use App\Models\Street;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\AccessSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;

test('a save and its audit entry roll back together', function () {
    try {
        DB::transaction(function () {
            Street::query()->create([
                'name' => 'Oak Lane',
                'is_active' => true,
            ]);

            expect(AuditLog::query()->where('auditable_type', Street::class)->count())->toBe(1);

            throw new RuntimeException('stop');
        });
    } catch (RuntimeException) {
        // The transaction is rolled back, including the audit row.
    }

    expect(Street::query()->count())->toBe(0);
    expect(AuditLog::query()->where('auditable_type', Street::class)->count())->toBe(0);
});

test('an update appears in the record change history', function () {
    $this->seed(AccessSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);
    $this->actingAs($admin);

    $street = Street::query()->create([
        'name' => 'Oak Lane',
        'is_active' => true,
    ]);
    $street->update(['name' => 'Pine Lane']);

    Livewire::test('record-drawer')
        ->dispatch('open-record', title: 'Pine Lane', type: Street::class, id: $street->id)
        ->assertSee('Create')
        ->assertSee('Update')
        ->assertSee('Oak Lane')
        ->assertSee('Pine Lane');
});

test('signing in records the user, ip, and guardhouse tablet', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->post(route('login.store'), [
        'email' => $guard->email,
        'password' => 'password',
    ]);

    $log = AuditLog::query()->where('action', AuditAction::Login)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($guard->id)
        ->and($log->device_label)->toBe('Guardhouse Tablet')
        ->and($log->ip_address)->not->toBeEmpty();
});

test('payment void and export entries can be written for later modules', function () {
    $street = Street::query()->create([
        'name' => 'Oak Lane',
        'is_active' => true,
    ]);

    $logger = app(AuditLogger::class);

    expect($logger->payment($street, [], ['amount' => '10.00'])->action)->toBe(AuditAction::Payment)
        ->and($logger->void($street, ['amount' => '10.00'], [])->action)->toBe(AuditAction::Void)
        ->and($logger->export($street, 'Street list')->action)->toBe(AuditAction::Export);
});

test('audit entries cannot be updated or deleted', function () {
    $street = Street::query()->create([
        'name' => 'Oak Lane',
        'is_active' => true,
    ]);

    $log = AuditLog::query()->where('auditable_type', Street::class)->firstOrFail();
    $admin = User::factory()->create();
    $this->seed(AccessSeeder::class);
    $admin->assignRole(RoleName::Administrator);

    expect($admin->can('update', $log))->toBeFalse()
        ->and($admin->can('delete', $log))->toBeFalse();

    expect(fn () => $log->update(['summary' => 'changed']))->toThrow(RuntimeException::class);
    expect(fn () => $log->delete())->toThrow(RuntimeException::class);

    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['summary' => 'changed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->delete())
        ->toThrow(QueryException::class);

    expect(AuditLog::query()->whereKey($log->id)->value('summary'))->not->toBe('changed');
    expect(Device::query()->count())->toBe(0);
});

test('change history hides money from a user without the financial permission', function () {
    $this->seed(AccessSeeder::class);

    $type = TransactionType::query()->create([
        'name' => 'Gate Sticker',
        'default_fee' => '98765.43',
        'is_active' => true,
    ]);

    $staff = User::factory()->create();
    $staff->assignRole(RoleName::SecurityGuard);
    $permission = Permission::query()->where('name', 'audit.view')->firstOrFail();
    $staff->roles()->firstOrFail()->permissions()->attach($permission->id);
    $staff->unsetRelation('roles');

    $this->actingAs($staff);

    Livewire::test('record-drawer')
        ->dispatch('open-record', title: 'Gate Sticker', type: TransactionType::class, id: $type->id)
        ->assertSee('Gate Sticker')
        ->assertDontSee('98765.43')
        ->assertDontSee('₱');
});
