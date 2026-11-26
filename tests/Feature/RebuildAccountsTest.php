<?php

use App\Actions\ChargeMembershipFee;
use App\Enums\PenaltyType;
use App\Models\AdvancePaymentEnrollment;
use App\Models\AdvancePaymentPromo;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\PropertyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2025-04-15'));
    ChargeType::factory()->create(['name' => 'Membership Fee']);
    MonthlyFeeRate::factory()->create(['amount' => '500.00', 'effective_from' => '2025-01-01']);
    Setting::current()->update(['due_day' => 10, 'grace_days' => 0, 'penalty_type' => PenaltyType::Percent, 'penalty_percent' => '3.00', 'membership_fee' => '0.00']);

    $this->property = Property::factory()->create();
    app(PropertyRegistry::class)->transferOwnership($this->property, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'ownership_type' => 'Sole Owner',
        'started_on' => '2025-01-01',
    ]);
});

function rebuiltStatement(Property $property, int $month): DuesStatement
{
    return $property->statements()->whereHas('billingPeriod', fn ($query) => $query->where('year', 2025)->where('month', $month))->sole();
}

function finalizeBillingRules(): void
{
    Setting::current()->update(['due_day' => 5, 'penalty_type' => PenaltyType::Fixed, 'penalty_fixed_amount' => '50.00', 'membership_fee' => '1000.00']);
}

test('bills missing months, reprices billed ones and charges the membership fee from the finalized rules', function () {
    app(BillingService::class)->generatePeriod(2025, 3);
    finalizeBillingRules();

    $this->artisan('billing:rebuild-accounts', ['--from' => '2025-01', '--monthly-fee' => '800', '--force' => true])
        ->assertSuccessful();

    $statements = $this->property->statements()->with('billingPeriod')->get();
    expect($statements)->toHaveCount(4)
        ->and($statements->pluck('regular')->unique()->all())->toBe(['800.00'])
        ->and(MonthlyFeeRate::amountFor(2025, 1))->toBe('800.00');

    $march = rebuiltStatement($this->property, 3);
    expect($march->due_on->toDateString())->toBe('2025-04-05')
        ->and($march->penalty_type)->toBe(PenaltyType::Fixed)
        ->and($march->penalty_rate)->toBe('50.00')
        ->and(rebuiltStatement($this->property, 1)->penalty)->toBe('150.00');

    $fee = Charge::query()->where('description', ChargeMembershipFee::DESCRIPTION)->sole();
    expect($fee->property_id)->toBe($this->property->id)
        ->and($fee->amount)->toBe('1000.00')
        ->and($fee->due_on->toDateString())->toBe('2025-01-01');
});

test('running the rebuild again with the same rules changes nothing', function () {
    finalizeBillingRules();
    $billing = app(BillingService::class);
    $billing->rebuildAccounts(CarbonImmutable::parse('2025-01-01'), '800.00');

    expect($billing->rebuildAccounts(CarbonImmutable::parse('2025-01-01'), '800.00'))->toMatchArray([
        'statements_created' => 0,
        'statements_repriced' => 0,
        'membership_fees_created' => 0,
        'membership_fees_updated' => 0,
    ]);
});

test('reprices an existing membership fee to the finalized amount and start of ownership', function () {
    $fee = app(BillingService::class)->createCharge($this->property, ChargeMembershipFee::DESCRIPTION, '500.00', '2025-04-01');
    finalizeBillingRules();

    app(BillingService::class)->rebuildAccounts(CarbonImmutable::parse('2025-01-01'));

    expect(Charge::query()->where('description', ChargeMembershipFee::DESCRIPTION)->sole()->id)->toBe($fee->id)
        ->and($fee->fresh()->amount)->toBe('1000.00')
        ->and($fee->fresh()->due_on->toDateString())->toBe('2025-01-01');
});

test('leaves a waived membership fee alone and does not charge it again', function () {
    $fee = app(BillingService::class)->createCharge($this->property, ChargeMembershipFee::DESCRIPTION, '500.00', '2025-04-01');
    $fee->update(['status' => 'Waived']);
    finalizeBillingRules();

    app(BillingService::class)->rebuildAccounts(CarbonImmutable::parse('2025-01-01'));

    expect(Charge::query()->where('description', ChargeMembershipFee::DESCRIPTION)->sole()->amount)->toBe('500.00');
});

test('keeps the monthly fee locked in by an advance payment promo', function () {
    $this->travelTo(Carbon::parse('2025-02-15'));
    app(BillingService::class)->generatePeriod(2025, 2);
    AdvancePaymentEnrollment::factory()->create([
        'property_id' => $this->property->id,
        'payment_id' => Payment::factory()->for($this->property)->create(['status' => 'Posted'])->id,
        'advance_payment_promo_id' => AdvancePaymentPromo::factory()->create(['covers_from' => '2025-01-01', 'covers_to' => '2025-12-01'])->id,
        'monthly_fee' => '450.00',
    ]);

    app(BillingService::class)->rebuildAccounts(CarbonImmutable::parse('2025-02-01'), '800.00');

    expect(rebuiltStatement($this->property, 2)->regular)->toBe('450.00');
});

test('a dry run reports the changes without saving them', function () {
    finalizeBillingRules();

    $this->artisan('billing:rebuild-accounts', ['--from' => '2025-01', '--monthly-fee' => '800', '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing was saved.')
        ->assertSuccessful();

    expect(DuesStatement::query()->count())->toBe(0)
        ->and(Charge::query()->count())->toBe(0)
        ->and(MonthlyFeeRate::amountFor(2025, 1))->toBe('500.00');
});

test('rejects an invalid starting month or monthly fee', function (array $options) {
    $this->artisan('billing:rebuild-accounts', [...$options, '--force' => true])->assertFailed();

    expect(DuesStatement::query()->count())->toBe(0);
})->with([
    'month in the future' => [['--from' => '2025-05']],
    'not a month' => [['--from' => '2025-1-1']],
    'negative fee' => [['--monthly-fee' => '-1']],
]);
