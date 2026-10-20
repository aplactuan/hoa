<?php

use App\Models\Correspondence;
use App\Models\Facility;
use App\Models\Property;
use App\Models\RequestCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\BillingService;
use App\Services\OfficeService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

test('a fee transaction cannot finish until its charge is paid', function () {
    $property = Property::factory()->create();
    $type = TransactionType::factory()->create(['name' => 'Clearance', 'default_fee' => '200.00']);
    $office = app(OfficeService::class);
    $transaction = $office->createTransaction($property, $type, 'Maria Santos');

    expect($transaction->charge?->amount)->toBe('200.00');
    expect(fn () => $office->completeTransaction($transaction))->toThrow(ValidationException::class);

    app(BillingService::class)->recordPayment($property, [
        'amount' => '200.00',
        'method' => 'Cash',
        'paid_on' => now()->toDateString(),
    ]);
    $office->completeTransaction($transaction->fresh());

    expect($transaction->fresh()->status)->toBe('Completed');
});

test('a case keeps update history and requires action taken to resolve', function () {
    $property = Property::factory()->create();
    $category = RequestCategory::factory()->create();
    $office = app(OfficeService::class);
    $case = $office->logCase($property, $category, 'Tenant', 'Streetlight is out', 'High');

    $office->addCaseUpdate($case, 'Electrician assigned');
    expect(fn () => $office->resolveCase($case->fresh(), ''))->toThrow(ValidationException::class);
    $office->resolveCase($case->fresh(), 'Lamp replaced');

    expect($case->fresh()->status)->toBe('Resolved')
        ->and($case->updates()->count())->toBe(2)
        ->and($case->fresh()->action_taken)->toBe('Lamp replaced');
});

test('issuing a violation notice creates one correspondence and one fine', function () {
    $property = Property::factory()->create();
    $type = ViolationType::factory()->create(['default_fine' => '500.00']);
    $office = app(OfficeService::class);
    $violation = $office->recordViolation($property, $type, 'Blocked driveway');

    $office->issueNotice($violation);

    expect($violation->fresh()->status)->toBe('Notice Sent')
        ->and($violation->fresh()->charge?->amount)->toBe('500.00')
        ->and(Correspondence::query()->where('property_id', $property->id)->count())->toBe(1);
    expect(fn () => $office->issueNotice($violation->fresh()))->toThrow(ValidationException::class);
});

test('overlapping facility bookings are rejected and approval creates fee and deposit charges', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $property = Property::factory()->create();
    $facility = Facility::factory()->create(['fee' => '1000.00', 'deposit' => '500.00']);
    $administrator = User::factory()->create();
    $office = app(OfficeService::class);
    $reservation = $office->bookFacility($property, $facility, '2026-11-01', '10:00', '12:00', 'Family event', 20);

    expect(fn () => $office->bookFacility($property, $facility, '2026-11-01', '11:00', '13:00', 'Other event', 10))
        ->toThrow(ValidationException::class);
    $office->approveReservation($reservation, $administrator->id);

    expect($reservation->fresh()->approval_status)->toBe('Approved')
        ->and($reservation->fresh()->feeCharge?->amount)->toBe('1000.00')
        ->and($reservation->fresh()->depositCharge?->amount)->toBe('500.00');
});

test('facility deposit can be refunded only after the event and payment', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $property = Property::factory()->create();
    $facility = Facility::factory()->create(['fee' => '0.00', 'deposit' => '500.00']);
    $treasurer = User::factory()->create();
    $office = app(OfficeService::class);
    $reservation = $office->bookFacility($property, $facility, '2026-10-15', '10:00', '12:00', 'Party', 20);
    $office->approveReservation($reservation, $treasurer->id);

    expect(fn () => $office->refundDeposit($reservation->fresh(), 'RV-001', $treasurer->id))->toThrow(ValidationException::class);
    $this->travelTo(Carbon::parse('2026-10-16'));
    expect(fn () => $office->refundDeposit($reservation->fresh(), 'RV-001', $treasurer->id))->toThrow(ValidationException::class);

    app(BillingService::class)->recordPayment($property, ['amount' => '500.00', 'method' => 'Cash', 'paid_on' => '2026-10-16']);
    $office->refundDeposit($reservation->fresh(), 'RV-001', $treasurer->id);

    expect($reservation->fresh()->payment_status)->toBe('Refunded')
        ->and($reservation->fresh()->depositCharge->status)->toBe('Refunded')
        ->and($reservation->fresh()->refund_reference)->toBe('RV-001');
    expect(fn () => $office->refundDeposit($reservation->fresh(), 'RV-002', $treasurer->id))->toThrow(ValidationException::class);
});
