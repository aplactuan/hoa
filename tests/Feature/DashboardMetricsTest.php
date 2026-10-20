<?php

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DashboardService;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Carbon;

test('dashboard metrics use the same billed and allocated values as the account', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $this->seed(AccessSeeder::class);
    $property = Property::factory()->create(['lot_area' => '10.00', 'status' => 'Occupied']);
    $billing = app(BillingService::class);
    $billing->generatePeriod(2026, 10);
    $billing->recordPayment($property, ['amount' => '40.00', 'paid_on' => '2026-10-14', 'method' => 'Cash']);
    $staff = User::factory()->create();
    $staff->assignRole(RoleName::OfficeStaff);

    $overview = app(DashboardService::class)->overview();
    expect($overview['properties'])->toBe(1)
        ->and($overview['occupied'])->toBe(1)
        ->and($overview['outstanding_cents'])->toBe(6000)
        ->and($overview['collection_percent'])->toBe(40.0);

    $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('₱60.00');
});
