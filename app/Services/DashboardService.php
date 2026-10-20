<?php

namespace App\Services;

use App\Models\DuesStatement;
use App\Models\FacilityReservation;
use App\Models\HouseStaff;
use App\Models\OfficeTransaction;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ServiceCase;
use App\Models\VehicleSticker;
use App\Models\Violation;
use App\Support\BillingMath;

class DashboardService
{
    public function __construct(private AccountService $accounts) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $properties = Property::query()->with([
            'currentOwner',
            'statements.allocations.payment',
            'statements.adjustments',
            'charges.allocations.payment',
            'payments.allocations',
        ])->get();
        $accounts = $properties->map(function (Property $property): array {
            return ['property' => $property, 'summary' => $this->accounts->summary($property, useLoaded: true)];
        });
        $monthStatements = DuesStatement::query()->with(['billingPeriod', 'allocations.payment'])
            ->whereHas('billingPeriod', fn ($query) => $query->where('year', now()->year)->where('month', now()->month))->get();
        $billed = $monthStatements->sum(fn (DuesStatement $statement): int => BillingMath::cents($statement->regular));
        $collected = $monthStatements->sum(fn (DuesStatement $statement): int => $statement->allocations
            ->filter(fn ($allocation): bool => $allocation->payment?->status !== 'Voided')
            ->sum(fn ($allocation): int => BillingMath::cents($allocation->amount)));
        $collections = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $payments = Payment::query()->where('status', '!=', 'Voided')
                ->whereYear('paid_on', $month->year)->whereMonth('paid_on', $month->month)->get();
            $collections[] = [
                'label' => $month->format('M'),
                'cents' => $payments->sum(fn (Payment $payment): int => BillingMath::cents($payment->amount)),
            ];
        }

        return [
            'properties' => $properties->count(),
            'occupied' => $properties->where('status', 'Occupied')->count(),
            'vacant' => $properties->where('status', 'Vacant')->count(),
            'outstanding_cents' => $accounts->sum(fn (array $account): int => $account['summary']['outstanding_cents']),
            'good_standing' => $accounts->filter(fn (array $account): bool => $account['summary']['standing'] === 'Good Standing')->count(),
            'collection_percent' => $billed > 0 ? round($collected / $billed * 100, 1) : 0,
            'payments_recorded' => Payment::query()->where('status', '!=', 'Voided')->count(),
            'top_arrears' => $accounts->sortByDesc(fn (array $account): int => $account['summary']['outstanding_cents'])->take(6)->values(),
            'open_cases' => ServiceCase::query()->with('property')->whereIn('status', ['Open', 'In Progress'])->latest()->limit(5)->get(),
            'upcoming_reservations' => FacilityReservation::query()->with('facility')->whereDate('event_on', '>=', now()->toDateString())->whereIn('approval_status', ['Pending', 'Approved'])->orderBy('event_on')->limit(5)->get(),
            'collections' => $collections,
            'attention' => [
                'transactions' => OfficeTransaction::query()->whereIn('status', ['Pending', 'In Progress'])->count(),
                'requests' => ServiceCase::query()->whereIn('status', ['Open', 'In Progress'])->count(),
                'violations' => Violation::query()->whereIn('status', ['Open', 'Notice Sent', 'Escalated'])->count(),
                'reservations' => FacilityReservation::query()->where('approval_status', 'Pending')->count(),
                'stickers' => VehicleSticker::query()->where('status', 'Active')->whereDate('expires_on', '<=', now()->addDays(45))->count(),
                'staff_passes' => HouseStaff::query()->where('status', 'Active')->whereDate('pass_expires_on', '<=', now()->addDays(45))->count(),
            ],
        ];
    }
}
