<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Carbon;

test('authorized users receive PDF statements and receipts while guards cannot', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create(['lot_area' => '10.00']);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2026, 10);
    $statement = $property->statements()->sole();
    $payment = $billing->recordPayment($property, ['amount' => '40.00', 'paid_on' => '2026-10-14', 'method' => 'Cash']);
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleName::Treasurer);
    $guard = User::factory()->create();
    $guard->assignRole(RoleName::SecurityGuard);

    $statementResponse = $this->actingAs($treasurer)->get(route('statements.print', $statement))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $receiptResponse = $this->actingAs($treasurer)->get(route('receipts.print', $payment))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($statementResponse->getContent())->toStartWith('%PDF-')
        ->and($receiptResponse->getContent())->toStartWith('%PDF-');

    $this->actingAs($guard)->get(route('statements.print', $statement))->assertForbidden();
    $this->actingAs($guard)->get(route('receipts.print', $payment))->assertForbidden();
});
