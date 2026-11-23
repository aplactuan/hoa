<?php

use App\Actions\ChargeMembershipFee;
use App\Actions\LinkOwnerUser;
use App\Actions\RecordPropertyOwner;
use App\Enums\DiscountType;
use App\Enums\PenaltyType;
use App\Enums\RoleName;
use App\Jobs\RecalculateAccounts;
use App\Models\AdvancePaymentEnrollment;
use App\Models\AdvancePaymentPromo;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\PenaltyAmnesty;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BillingService;
use App\Services\PropertyRegistry;
use App\Services\StatementOfAccount;
use Database\Seeders\AccessSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    MonthlyFeeRate::factory()->create(['amount' => '500.00', 'effective_from' => '2025-01-01']);
    Setting::current()->update(['due_day' => 10, 'grace_days' => 0, 'penalty_type' => PenaltyType::Percent, 'penalty_percent' => '3.00']);
});

function ownerUserFor(Property ...$properties): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Owner);

    foreach ($properties as $property) {
        $owner = app(PropertyRegistry::class)->transferOwnership($property, [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'ownership_type' => 'Sole Owner',
            'started_on' => '2025-01-01',
        ]);
        app(LinkOwnerUser::class)->handle($user, $owner);
    }

    return $user;
}

function statementFor(Property $property, int $year, int $month): DuesStatement
{
    return $property->statements()->whereHas('billingPeriod', fn ($query) => $query->where('year', $year)->where('month', $month))->sole();
}

/**
 * @param  list<int>  $months
 */
function generateMonths(int $year, array $months): void
{
    foreach ($months as $month) {
        app(BillingService::class)->generatePeriod($year, $month);
    }
}

/**
 * @return array<string, mixed>
 */
function accountFor(Property $property, ?string $asOf = null): array
{
    return app(StatementOfAccount::class)->forProperties(Property::query()->whereKey($property->id)->get(), $asOf ? Carbon::parse($asOf) : null);
}

test('an owner of several properties pays the flat monthly dues only once', function () {
    [$first, $second] = Property::factory()->count(2)->create();
    $unowned = Property::factory()->create();
    ownerUserFor($first, $second);

    app(BillingService::class)->generatePeriod(2025, 1);

    expect(statementFor($first, 2025, 1)->regular)->toBe('500.00')
        ->and(statementFor($second, 2025, 1)->regular)->toBe('0.00')
        ->and(statementFor($unowned, 2025, 1)->regular)->toBe('500.00')
        ->and(statementFor($first, 2025, 1)->due_on->toDateString())->toBe('2025-02-10');
});

test('a monthly dues change applies from its effective month onward', function () {
    MonthlyFeeRate::factory()->create(['amount' => '600.00', 'effective_from' => '2025-03-01']);
    $property = Property::factory()->create();

    generateMonths(2025, [1, 2, 3]);

    expect(statementFor($property, 2025, 1)->regular)->toBe('500.00')
        ->and(statementFor($property, 2025, 2)->regular)->toBe('500.00')
        ->and(statementFor($property, 2025, 3)->regular)->toBe('600.00');
});

test('each unpaid month is penalized every month after its due date and payments settle dues before penalties', function () {
    $this->travelTo(Carbon::parse('2025-04-20'));
    $property = Property::factory()->create();
    generateMonths(2025, [1, 2, 3, 4]);

    expect(statementFor($property, 2025, 1)->penalty)->toBe('45.00')
        ->and(statementFor($property, 2025, 2)->penalty)->toBe('30.00')
        ->and(statementFor($property, 2025, 3)->penalty)->toBe('15.00')
        ->and(statementFor($property, 2025, 4)->penalty)->toBe('0.00');

    app(BillingService::class)->recordPayment($property, ['amount' => '1530.00', 'paid_on' => '2025-04-20', 'method' => 'Cash']);

    $account = accountFor($property);
    expect(collect($account['months'])->pluck('status', 'month')->all())->toBe([
        'Jan 2025' => 'Dues paid · penalty unpaid',
        'Feb 2025' => 'Dues paid · penalty unpaid',
        'Mar 2025' => 'Dues paid · penalty unpaid',
        'Apr 2025' => 'Due by May 10',
    ])->and($account['balance_cents'])->toBe(56000)
        ->and($account['payments'][0]['applied_to'])->toBe([
            ['label' => 'Dues – Jan 2025', 'cents' => 50000],
            ['label' => 'Dues – Feb 2025', 'cents' => 50000],
            ['label' => 'Dues – Mar 2025', 'cents' => 50000],
            ['label' => 'Dues – Apr 2025', 'cents' => 3000],
        ]);

    $this->travelTo(Carbon::parse('2025-05-20'));
    app(BillingService::class)->generatePeriod(2025, 5);
    (new RecalculateAccounts)->handle(app(BillingService::class));

    expect(statementFor($property, 2025, 1)->penalty)->toBe('45.00')
        ->and(statementFor($property, 2025, 4)->penalty)->toBe('15.00');
});

test('a fixed penalty respects grace days and keeps the rule it was billed under', function () {
    $this->travelTo(Carbon::parse('2025-03-20'));
    Setting::current()->update(['penalty_type' => PenaltyType::Fixed, 'penalty_fixed_amount' => '150.00', 'grace_days' => 5]);
    $property = Property::factory()->create();
    generateMonths(2025, [1, 2, 3]);

    expect(statementFor($property, 2025, 1)->penalty)->toBe('300.00')
        ->and(statementFor($property, 2025, 2)->penalty)->toBe('150.00')
        ->and(statementFor($property, 2025, 3)->penalty)->toBe('0.00');

    Setting::current()->update(['penalty_type' => PenaltyType::Percent, 'penalty_percent' => '50.00']);
    app(BillingService::class)->recordPayment($property, ['amount' => '100.00', 'paid_on' => '2025-03-20', 'method' => 'Cash']);

    expect(statementFor($property, 2025, 1)->penalty)->toBe('300.00');
});

test('charges such as the membership fee never carry a penalty', function () {
    $this->travelTo(Carbon::parse('2025-01-05'));
    $property = Property::factory()->create();
    app(BillingService::class)->createCharge($property, 'Membership fee', '1000.00', '2025-01-05');

    $this->travelTo(Carbon::parse('2025-06-20'));
    $account = accountFor($property);

    expect(collect($account['lines'])->where('kind', 'penalty'))->toBeEmpty()
        ->and($account['charges'][0]['status'])->toBe('Unpaid · no penalty')
        ->and($account['balance_cents'])->toBe(100000);
});

test('a back-dated payment removes penalties it would have prevented and voiding it restores them', function () {
    $this->travelTo(Carbon::parse('2025-04-20'));
    $property = Property::factory()->create();
    $billing = app(BillingService::class);
    generateMonths(2025, [1, 2, 3, 4]);

    expect(fn () => $billing->recordPayment($property, ['amount' => '2500.00', 'paid_on' => '2025-02-01', 'method' => 'Cash']))
        ->toThrow(ValidationException::class);

    $payment = $billing->recordPayment($property, ['amount' => '2500.00', 'paid_on' => '2025-02-01', 'method' => 'Cash', 'remarks' => 'Deposit slip submitted late']);

    expect(statementFor($property, 2025, 1)->penalty)->toBe('0.00')
        ->and(statementFor($property, 2025, 2)->penalty)->toBe('0.00')
        ->and(statementFor($property, 2025, 3)->penalty)->toBe('0.00')
        ->and(statementFor($property, 2025, 1)->remarks)->toContain('Penalty recalculated from ₱45.00 to ₱0.00')
        ->and(app(AccountService::class)->summary($property))->toMatchArray(['outstanding_cents' => 0, 'credit_cents' => 50000]);

    $billing->requestVoid($payment, 'Bounced deposit', User::factory()->create()->id);
    $billing->approveVoid($payment->fresh(), User::factory()->create()->id);

    expect(statementFor($property, 2025, 1)->penalty)->toBe('45.00')
        ->and(statementFor($property, 2025, 3)->penalty)->toBe('15.00')
        ->and(app(AccountService::class)->summary($property)['outstanding_cents'])->toBe(209000);
});

test('a penalty amnesty waives penalties on dues paid by its end date and reinstates them on dues still unpaid', function () {
    $this->travelTo(Carbon::parse('2025-04-15'));
    PenaltyAmnesty::factory()->create(['name' => 'Q1 amnesty', 'ends_on' => '2025-03-31']);
    [$paidOnTime, $unpaid] = Property::factory()->count(2)->create();
    generateMonths(2025, [1, 2, 3, 4]);
    app(BillingService::class)->recordPayment($paidOnTime, ['amount' => '1500.00', 'paid_on' => '2025-03-20', 'method' => 'Cash']);

    $waived = accountFor($paidOnTime);
    expect(statementFor($paidOnTime, 2025, 1)->penalty)->toBe('0.00')
        ->and($waived['months'][0]['status'])->toBe('Paid · penalty waived')
        ->and($waived['waived_penalties_cents'])->toBe(4500)
        ->and(collect($waived['lines'])->pluck('description'))->toContain('Penalty waived – Jan 2025 dues paid by Mar 31, 2025 (30.00 waived)');

    expect(statementFor($unpaid, 2025, 1)->penalty)->toBe('45.00')
        ->and(statementFor($unpaid, 2025, 2)->penalty)->toBe('30.00')
        ->and(collect(accountFor($unpaid)['lines'])->pluck('description'))->toContain('Penalties reinstated – Jan 2025 dues (2 months late, not paid by Mar 31, 2025)');

    $beforeTheAmnestyEnds = accountFor($unpaid, '2025-03-25');
    expect($beforeTheAmnestyEnds['billed_penalties_cents'])->toBe(0)
        ->and($beforeTheAmnestyEnds['pending_penalties_cents'])->toBe(4500);
});

test('paying the fee and the whole covered year by the deadline earns the early payment discount automatically', function () {
    $this->travelTo(Carbon::parse('2025-01-05'));
    [$prepaid, $short, $late] = Property::factory()->count(3)->create();
    $billing = app(BillingService::class);
    $billing->createCharge($prepaid, 'Membership fee', '1000.00', '2025-01-05');
    AdvancePaymentPromo::factory()->create([
        'name' => 'Full year 2025',
        'discount_type' => DiscountType::Fixed,
        'discount_value' => '500.00',
        'pay_by' => '2025-02-05',
        'covers_from' => '2025-01-01',
        'covers_to' => '2025-12-01',
    ]);
    $billing->generatePeriod(2025, 1);
    $billing->recordPayment($prepaid, ['amount' => '1500.00', 'paid_on' => '2025-01-05', 'method' => 'Cash']);

    expect(accountFor($prepaid)['promos'][0])->toMatchArray(['required_cents' => 650000, 'remaining_cents' => 500000, 'qualified' => false]);

    $this->travelTo(Carbon::parse('2025-02-03'));
    $billing->generatePeriod(2025, 2);
    $billing->recordPayment($prepaid, ['amount' => '5000.00', 'paid_on' => '2025-02-03', 'method' => 'Cash']);
    $billing->recordPayment($short, ['amount' => '5400.00', 'paid_on' => '2025-02-03', 'method' => 'Cash']);
    $this->travelTo(Carbon::parse('2025-02-06'));
    $billing->recordPayment($late, ['amount' => '5500.00', 'paid_on' => '2025-02-06', 'method' => 'Cash']);

    expect(AdvancePaymentEnrollment::query()->pluck('property_id')->all())->toBe([$prepaid->id]);

    MonthlyFeeRate::factory()->create(['amount' => '600.00', 'effective_from' => '2025-06-01']);
    generateMonths(2025, range(3, 12));

    expect(statementFor($prepaid, 2025, 6)->regular)->toBe('500.00')
        ->and(statementFor($prepaid, 2025, 12)->credits)->toBe('500.00')
        ->and(app(AccountService::class)->summary($prepaid))->toMatchArray(['outstanding_cents' => 0, 'credit_cents' => 0])
        ->and(statementFor($short, 2025, 6)->regular)->toBe('600.00');

    $this->travelTo(Carbon::parse('2025-12-31'));
    expect(accountFor($prepaid))->toMatchArray(['discount_cents' => 50000, 'balance_cents' => 0])
        ->and(accountFor($short)['discount_cents'])->toBe(0)
        ->and(accountFor($late)['discount_cents'])->toBe(0);
});

test('the statement of account shows the ledger with a running balance and can be viewed as of an earlier date', function () {
    $this->travelTo(Carbon::parse('2025-03-15'));
    $property = Property::factory()->create();
    generateMonths(2025, [1, 2, 3]);
    app(BillingService::class)->recordPayment($property, ['amount' => '500.00', 'paid_on' => '2025-03-01', 'method' => 'Cash']);

    $account = accountFor($property);

    expect(collect($account['lines'])->map(fn (array $line): array => [$line['description'], $line['debit_cents'], $line['credit_cents'], $line['balance_cents']])->all())->toBe([
        ['Monthly dues – Jan 2025 (due Feb 10, 2025)', 50000, 0, 50000],
        ['Monthly dues – Feb 2025 (due Mar 10, 2025)', 50000, 0, 100000],
        ['Late penalty – Jan 2025 dues (1st month late)', 1500, 0, 101500],
        ['Monthly dues – Mar 2025 (due Apr 10, 2025)', 50000, 0, 151500],
        ['Payment received', 0, 50000, 101500],
        ['Late penalty – Feb 2025 dues (1st month late)', 1500, 0, 103000],
    ])->and($account)->toMatchArray([
        'balance_cents' => 103000,
        'overdue_cents' => 53000,
        'not_yet_due_cents' => 50000,
        'next_due_on' => '2025-04-10',
    ])->and(collect($account['months'])->pluck('status')->all())->toBe(['Dues paid · penalty unpaid', 'Overdue · 1 mo', 'Due by Apr 10']);

    $earlier = accountFor($property, '2025-02-20');
    expect($earlier['balance_cents'])->toBe(101500)
        ->and($earlier['payments'])->toBeEmpty()
        ->and($earlier['future_payments'])->toBe(1);
});

test('the membership fee is charged once when a new member joins', function () {
    Notification::fake();
    $this->seed(MasterDataSeeder::class);
    Setting::current()->update(['membership_fee' => '1000.00']);
    [$first, $second] = Property::factory()->count(2)->create();
    $recordOwner = app(RecordPropertyOwner::class);
    $owner = ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'ownership_type' => 'Sole Owner', 'started_on' => now()->toDateString(), 'email' => 'juan@example.com'];

    $recordOwner->handle($first, $owner);
    $recordOwner->handle($second, $owner);

    $fees = Charge::query()->where('description', ChargeMembershipFee::DESCRIPTION)->get();
    expect($fees)->toHaveCount(1)
        ->and($fees->sole()->property_id)->toBe($first->id)
        ->and($fees->sole()->amount)->toBe('1000.00')
        ->and($fees->sole()->charge_type_id)->toBe(ChargeType::query()->where('name', 'Membership Fee')->value('id'));
});

test('a lump-sum payment settles dues across all of the owner properties and keeps the rest as credit', function () {
    $this->travelTo(Carbon::parse('2025-01-20'));
    [$first, $second] = Property::factory()->count(2)->create();
    $user = ownerUserFor($first, $second);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2025, 1);
    $charge = $billing->createCharge($second, 'Gate pass', '300.00', '2025-01-30');

    $billing->recordPayment($first, ['amount' => '1000.00', 'paid_on' => '2025-01-20', 'method' => 'Cash']);

    $account = app(StatementOfAccount::class)->forProperties($user->currentOwnedProperties()->get());
    expect(app(AccountService::class)->chargeBalanceCents($charge->fresh()))->toBe(0)
        ->and($account['balance_cents'])->toBe(-20000)
        ->and($account['credit_cents'])->toBe(20000);
});

test('owners see and export only their own statement of account', function () {
    $this->travelTo(Carbon::parse('2025-02-20'));
    $owned = Property::factory()->create();
    $foreign = Property::factory()->create();
    $user = ownerUserFor($owned);
    ownerUserFor($foreign);
    app(BillingService::class)->generatePeriod(2025, 1);

    $this->actingAs($user)->get(route('owner.account'))
        ->assertOk()
        ->assertSee('Monthly schedule')
        ->assertSee(statementFor($owned, 2025, 1)->code)
        ->assertDontSee(statementFor($foreign, 2025, 1)->code);

    $pdf = $this->actingAs($user)->get(route('owner.statement-of-account.print', ['as_of' => '2025-02-15']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');

    $csv = $this->actingAs($user)->get(route('owner.statement-of-account.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('Monthly dues – Jan 2025 (due Feb 10, 2025)')
        ->and($csv)->toContain('Late penalty – Jan 2025 dues (1st month late)');
});

test('staff with statement access can export a property owner statement of account and guards cannot', function () {
    $property = Property::factory()->create();
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $this->actingAs($treasurer)->get(route('properties.statement-of-account.print', $property))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($treasurer)->get(route('properties.statement-of-account.csv', $property))->assertOk();
    $this->actingAs($guard)->get(route('properties.statement-of-account.print', $property))->assertForbidden();
    $this->actingAs($guard)->get(route('properties.statement-of-account.csv', $property))->assertForbidden();
});

test('the property billing tab shows the owner statement of account', function () {
    $this->travelTo(Carbon::parse('2025-02-20'));
    $property = Property::factory()->create();
    app(BillingService::class)->generatePeriod(2025, 1);
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);

    Livewire::actingAs($treasurer)
        ->test('pages::property-profile', ['property' => $property])
        ->call('showTab', 'Billing')
        ->assertSee('Owner statement of account')
        ->assertSee('Overdue · 1 mo')
        ->set('soaTab', 'ledger')
        ->assertSee('Late penalty – Jan 2025 dues (1st month late)');
});

test('the statement matches the reference statement of account for the sample homeowner', function () {
    $this->travelTo(Carbon::parse('2026-05-20'));
    $property = Property::factory()->create();
    $billing = app(BillingService::class);
    PenaltyAmnesty::factory()->create(['name' => 'Penalty amnesty', 'ends_on' => '2026-03-31']);
    AdvancePaymentPromo::factory()->create([
        'discount_type' => DiscountType::Fixed,
        'discount_value' => '500.00',
        'pay_by' => '2025-02-05',
        'covers_from' => '2025-01-01',
        'covers_to' => '2025-12-01',
    ]);
    $billing->createCharge($property, 'Membership fee', '1000.00', '2025-01-05');
    generateMonths(2025, range(1, 12));
    generateMonths(2026, range(1, 5));

    foreach ([['2025-01-05', '1500.00'], ['2025-03-08', '1000.00'], ['2025-06-20', '1530.00'], ['2025-11-03', '2500.00'], ['2026-04-15', '2000.00']] as [$paidOn, $amount]) {
        $billing->recordPayment($property, ['amount' => $amount, 'paid_on' => $paidOn, 'method' => 'Cash', 'remarks' => 'Reference payment']);
    }

    $account = accountFor($property);

    expect($account)->toMatchArray([
        'balance_cents' => 113500,
        'billed_penalties_cents' => 16500,
        'paid_cents' => 853000,
        'credit_cents' => 0,
        'overdue_cents' => 63500,
        'waived_penalties_cents' => 13500,
        'pending_penalties_cents' => 0,
        'discount_cents' => 0,
    ])->and(collect($account['months'])->where('penalty_cents', '>', 0)->pluck('penalty_cents', 'month')->all())->toBe([
        'Dec 2025' => 6000,
        'Jan 2026' => 4500,
        'Feb 2026' => 3000,
        'Mar 2026' => 1500,
        'Apr 2026' => 1500,
    ])->and(collect($account['payments'])->map(fn (array $payment): array => collect($payment['applied_to'])->map(fn (array $applied): string => $applied['label'].' '.$applied['cents'] / 100)->all())->all())->toBe([
        ['Membership fee 1000', 'Dues – Jan 2025 500'],
        ['Dues – Feb 2025 500', 'Dues – Mar 2025 500'],
        ['Dues – Apr 2025 500', 'Dues – May 2025 500', 'Dues – Jun 2025 500', 'Advance / credit 30'],
        ['Dues – Jul 2025 470', 'Dues – Aug 2025 500', 'Dues – Sep 2025 500', 'Dues – Oct 2025 500', 'Dues – Nov 2025 500', 'Advance / credit 30'],
        ['Dues – Dec 2025 470', 'Dues – Jan 2026 500', 'Dues – Feb 2026 500', 'Dues – Mar 2026 500', 'Dues – Apr 2026 30'],
    ]);
});
