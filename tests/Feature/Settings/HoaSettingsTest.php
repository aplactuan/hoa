<?php

use App\Enums\AuditAction;
use App\Enums\PenaltyType;
use App\Enums\RoleName;
use App\Models\AdvancePaymentPromo;
use App\Models\AuditLog;
use App\Models\MonthlyFeeRate;
use App\Models\PenaltyAmnesty;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('an administrator can save association settings and the change is audited', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::settings.hoa')
        ->set('membership_fee', '500.00')
        ->set('penalty_type', 'fixed')
        ->set('penalty_fixed_amount', '150.00')
        ->set('penalty_percent', '3')
        ->set('due_day', 10)
        ->set('grace_days', 5)
        ->set('expiry_warning_days', 45)
        ->set('hoa_name', 'Green Meadows Homeowners Association')
        ->set('hoa_address', 'Phase 1, Green Meadows')
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::current();

    expect($setting->membership_fee)->toBe('500.00')
        ->and($setting->penalty_type)->toBe(PenaltyType::Fixed)
        ->and($setting->penalty_fixed_amount)->toBe('150.00')
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

test('an administrator can schedule a monthly fee change and create a promo but cannot backdate a rate', function () {
    $this->travelTo(Carbon::parse('2026-11-23'));
    $this->seed(AccessSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Administrator);

    Livewire::actingAs($admin)
        ->test('pages::settings.hoa')
        ->set('rateAmount', '1200.00')
        ->set('rateMonth', '2026-10')
        ->call('addRate')
        ->assertHasErrors('rateMonth')
        ->set('rateMonth', '2027-01')
        ->call('addRate')
        ->assertHasNoErrors()
        ->set('promoName', 'Pay ahead 2027')
        ->set('promoDiscountType', 'fixed')
        ->set('promoDiscountValue', '1000.00')
        ->set('promoPayBy', '2026-12-31')
        ->set('promoCoversFrom', '2027-01')
        ->set('promoCoversTo', '2027-12')
        ->call('addPromo')
        ->assertHasNoErrors()
        ->set('amnestyName', 'Year-end amnesty')
        ->set('amnestyEndsOn', '2026-12-31')
        ->call('addAmnesty')
        ->assertHasNoErrors();

    expect(MonthlyFeeRate::amountFor(2026, 12))->toBe('500.00')
        ->and(MonthlyFeeRate::amountFor(2027, 1))->toBe('1200.00')
        ->and(AdvancePaymentPromo::query()->sole()->coveredMonths())->toHaveCount(12)
        ->and(PenaltyAmnesty::query()->sole()->ends_on->toDateString())->toBe('2026-12-31');
});
