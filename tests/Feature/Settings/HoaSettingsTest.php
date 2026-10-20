<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\MasterDataSeeder;
use Livewire\Livewire;

test('an administrator can save association settings and the change is audited', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::settings.hoa')
        ->set('dues_rate_per_sqm', '18.75')
        ->set('penalty_percent', '3')
        ->set('due_day', 10)
        ->set('grace_days', 5)
        ->set('expiry_warning_days', 45)
        ->set('hoa_name', 'Green Meadows Homeowners Association')
        ->set('hoa_address', 'Phase 1, Green Meadows')
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::current();

    expect($setting->dues_rate_per_sqm)->toBe('18.75')
        ->and($setting->expiry_warning_days)->toBe(45)
        ->and($setting->due_day)->toBe(10);

    expect(
        AuditLog::query()
            ->where('auditable_type', Setting::class)
            ->where('action', AuditAction::Update)
            ->where('user_id', $admin->id)
            ->exists()
    )->toBeTrue();
});

test('the due day must stay within the first 28 days', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::settings.hoa')
        ->set('due_day', 31)
        ->call('save')
        ->assertHasErrors('due_day')
        ->assertSee('The due day field must be between 1 and 28.');
});
