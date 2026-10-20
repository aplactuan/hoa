<?php

use App\Enums\RoleName;
use App\Models\DuesStatement;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BillingService;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('statement generation is idempotent and carries an unpaid balance with penalty', function () {
    $property = Property::factory()->create(['lot_area' => '125.50']);
    $settings = Setting::current();
    $settings->update(['dues_rate_per_sqm' => '10.00', 'penalty_percent' => '2.00', 'due_day' => 15]);
    $billing = app(BillingService::class);

    expect($billing->generatePeriod(2026, 10))->toBe(1)
        ->and($billing->generatePeriod(2026, 10))->toBe(0)
        ->and($billing->generatePeriod(2026, 11))->toBe(1);

    $statements = DuesStatement::query()->where('property_id', $property->id)->orderBy('due_on')->get();
    expect($statements)->toHaveCount(2)
        ->and($statements[0]->regular)->toBe('1255.00')
        ->and($statements[1]->previous_balance)->toBe('1255.00')
        ->and($statements[1]->penalty)->toBe('25.10');
    expect(app(AccountService::class)->summary($property)['outstanding_cents'])->toBe(253510);
});

test('payment allocates oldest first and approved void restores the balance', function () {
    $this->travelTo(Carbon::parse('2026-11-20'));
    $property = Property::factory()->create(['lot_area' => '100.00']);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2026, 10);
    $billing->generatePeriod(2026, 11);

    $payment = $billing->recordPayment($property, [
        'amount' => '500.00',
        'paid_on' => '2026-11-20',
        'method' => 'Cash',
    ]);

    expect($payment->or_no)->toBe('OR-000001')
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocations[0]->allocatable->due_on->toDateString())->toBe('2026-10-15');
    expect(app(AccountService::class)->summary($property)['outstanding_cents'])->toBe(152000);

    $treasurer = User::factory()->create();
    $administrator = User::factory()->create();
    $billing->requestVoid($payment, 'Wrong amount', $treasurer->id);
    $billing->approveVoid($payment->fresh(), $administrator->id);

    expect(app(AccountService::class)->summary($property)['outstanding_cents'])->toBe(202000);
});

test('advance payment becomes credit and is applied to the next statement', function () {
    $this->travelTo(Carbon::parse('2026-10-20'));
    $property = Property::factory()->create(['lot_area' => '10.00']);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2026, 10);

    $payment = $billing->recordPayment($property, [
        'amount' => '150.00',
        'paid_on' => '2026-10-20',
        'method' => 'Cash',
    ]);

    expect(app(AccountService::class)->summary($property)['credit_cents'])->toBe(5000);
    $billing->generatePeriod(2026, 11);
    expect($payment->fresh()->allocations)->toHaveCount(2)
        ->and(app(AccountService::class)->summary($property)['outstanding_cents'])->toBe(5000);
});

test('treasurer can record a payment through Livewire and office staff cannot', function () {
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create();
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    Livewire::actingAs($treasurer)->test('pages::payments')
        ->set('propertyId', (string) $property->id)
        ->set('amount', '250.00')
        ->set('paidOn', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($property->payments()->sole()->amount)->toBe('250.00');
    Livewire::actingAs($staff)->test('pages::payments')->call('save')->assertForbidden();
});

test('a security guard cannot load billing screens', function () {
    $this->seed(AccessSeeder::class);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->actingAs($guard)->get(route('statements.index'))->assertForbidden();
    $this->actingAs($guard)->get(route('receipts.index'))->assertForbidden();
    $this->actingAs($guard)->get(route('charges.index'))->assertForbidden();
});

test('credits and waivers cannot erase payments or exceed an open balance', function () {
    $property = Property::factory()->create(['lot_area' => '100.00']);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2026, 10);
    $statement = $property->statements()->sole();

    expect(fn () => $billing->requestAdjustment($statement, '1000.01', 'Correction', 1))
        ->toThrow(ValidationException::class);

    $charge = $billing->createCharge($property, 'Permit', '100.00', '2026-12-01');
    $billing->recordPayment($property, ['amount' => '1050.00', 'method' => 'Cash', 'paid_on' => now()->toDateString()]);

    expect(fn () => $billing->requestWaiver($charge->fresh(), 'Forgive balance', 1))
        ->toThrow(ValidationException::class);
});
