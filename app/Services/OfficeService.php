<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Correspondence;
use App\Models\Facility;
use App\Models\FacilityReservation;
use App\Models\OfficeTransaction;
use App\Models\Property;
use App\Models\RequestCategory;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\TransactionType;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Support\BillingMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficeService
{
    public function __construct(private DisplayCode $codes, private BillingService $billing, private AccountService $accounts, private OfficeAlerts $alerts) {}

    public function createTransaction(Property $property, TransactionType $type, string $requestedBy, ?int $userId = null): OfficeTransaction
    {
        return DB::transaction(function () use ($property, $type, $requestedBy, $userId): OfficeTransaction {
            $transaction = $property->transactions()->create([
                'code' => $this->codes->next('transaction'),
                'transaction_type_id' => $type->id,
                'requested_by' => $requestedBy,
                'amount' => $type->default_fee,
                'status' => 'Pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (BillingMath::cents($type->default_fee) > 0) {
                $charge = $this->billing->createCharge($property, $type->name, $type->default_fee, now()->toDateString(), $userId);
                $charge->update(['source_type' => OfficeTransaction::class, 'source_id' => $transaction->id]);
                $transaction->update(['charge_id' => $charge->id]);
            }

            return $transaction;
        });
    }

    public function completeTransaction(OfficeTransaction $transaction, ?int $userId = null): void
    {
        if ($transaction->status === 'Cancelled' || $transaction->status === 'Completed') {
            throw ValidationException::withMessages(['status' => 'This transaction cannot be completed.']);
        }

        if ($transaction->charge && $this->accounts->chargeBalanceCents($transaction->charge) > 0) {
            throw ValidationException::withMessages(['charge' => 'The fee must be paid before completion.']);
        }

        $transaction->update(['status' => 'Completed', 'completed_on' => now()->toDateString(), 'updated_by' => $userId]);
    }

    public function logCase(Property $property, ?RequestCategory $category, string $filedBy, string $description, string $priority, ?int $userId = null): ServiceCase
    {
        if (! in_array($priority, ['Low', 'Medium', 'High', 'Urgent'], true)) {
            throw ValidationException::withMessages(['priority' => 'Choose a valid priority.']);
        }

        return DB::transaction(function () use ($property, $category, $filedBy, $description, $priority, $userId): ServiceCase {
            $case = $property->cases()->create([
                'code' => $this->codes->next('case'),
                'request_category_id' => $category?->id,
                'filed_by' => $filedBy,
                'description' => $description,
                'priority' => $priority,
                'status' => 'Open',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($priority === 'Urgent') {
                $this->alerts->send([RoleName::Administrator, RoleName::OfficeStaff], 'Urgent case logged', $case->code.' needs attention.', 'requests.index');
            }

            return $case;
        });
    }

    public function addCaseUpdate(ServiceCase $case, string $note, ?int $userId = null): void
    {
        if ($case->status === 'Closed' || trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Reopen the case and enter an update.']);
        }

        DB::transaction(function () use ($case, $note, $userId): void {
            $case->updates()->create(['note' => $note, 'user_id' => $userId]);
            $case->update(['status' => 'In Progress', 'updated_by' => $userId]);
        });
    }

    public function resolveCase(ServiceCase $case, string $actionTaken, ?int $userId = null): void
    {
        if ($case->status === 'Closed' || trim($actionTaken) === '') {
            throw ValidationException::withMessages(['action_taken' => 'An open case and action taken are required.']);
        }

        DB::transaction(function () use ($case, $actionTaken, $userId): void {
            $case->updates()->create(['note' => $actionTaken, 'user_id' => $userId]);
            $case->update([
                'action_taken' => $actionTaken,
                'resolved_on' => now()->toDateString(),
                'status' => 'Resolved',
                'updated_by' => $userId,
            ]);
        });
    }

    public function recordViolation(Property $property, ViolationType $type, string $description, ?int $userId = null): Violation
    {
        return DB::transaction(fn (): Violation => $property->violations()->create([
            'code' => $this->codes->next('violation'),
            'violation_type_id' => $type->id,
            'description' => $description,
            'fine' => $type->default_fine,
            'status' => 'Open',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]));
    }

    public function issueNotice(Violation $violation, ?int $userId = null): Correspondence
    {
        if ($violation->status !== 'Open') {
            throw ValidationException::withMessages(['status' => 'Notice has already been issued.']);
        }

        return DB::transaction(function () use ($violation, $userId): Correspondence {
            $property = $violation->property;
            $notice = $property->correspondence()->create([
                'code' => $this->codes->next('correspondence'),
                'type' => 'Notice',
                'subject' => $violation->violationType->name,
                'sender' => Setting::current()->hoa_name,
                'recipient' => $property->currentOwner?->name ?? 'Property owner',
                'status' => 'Pending',
                'related_type' => Violation::class,
                'related_id' => $violation->id,
                'created_by' => $userId,
            ]);
            $chargeId = null;

            if (BillingMath::cents($violation->fine) > 0) {
                $charge = $this->billing->createCharge($property, 'Violation fine: '.$violation->violationType->name, $violation->fine, now()->addDays(15)->toDateString(), $userId);
                $charge->update(['source_type' => Violation::class, 'source_id' => $violation->id]);
                $chargeId = $charge->id;
            }

            $violation->update([
                'noticed_on' => now()->toDateString(),
                'deadline_on' => now()->addDays(15)->toDateString(),
                'charge_id' => $chargeId,
                'status' => 'Notice Sent',
                'updated_by' => $userId,
            ]);

            return $notice;
        });
    }

    public function bookFacility(Property $property, Facility $facility, string $eventOn, string $startsAt, string $endsAt, string $purpose, int $guests, ?int $userId = null): FacilityReservation
    {
        if ($eventOn < now()->toDateString() || $startsAt >= $endsAt || $guests < 0) {
            throw ValidationException::withMessages(['event_on' => 'Choose a future date and valid time range.']);
        }

        return DB::transaction(function () use ($property, $facility, $eventOn, $startsAt, $endsAt, $purpose, $guests, $userId): FacilityReservation {
            Facility::query()->lockForUpdate()->findOrFail($facility->id);
            $conflict = FacilityReservation::query()
                ->where('facility_id', $facility->id)
                ->whereDate('event_on', $eventOn)
                ->whereIn('approval_status', ['Pending', 'Approved'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'This facility is already booked for the selected time.']);
            }

            $reservation = $property->reservations()->create([
                'code' => $this->codes->next('reservation'),
                'facility_id' => $facility->id,
                'event_on' => $eventOn,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'purpose' => $purpose,
                'guests' => $guests,
                'fee' => $facility->fee,
                'deposit' => $facility->deposit,
                'approval_status' => 'Pending',
                'payment_status' => 'Unpaid',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->alerts->send([RoleName::Administrator], 'Reservation awaiting approval', $reservation->code.' needs a decision.', 'facilities.index');

            return $reservation;
        });
    }

    public function approveReservation(FacilityReservation $reservation, int $administratorId): void
    {
        if ($reservation->approval_status !== 'Pending') {
            throw ValidationException::withMessages(['approval_status' => 'Only pending reservations can be approved.']);
        }

        DB::transaction(function () use ($reservation, $administratorId): void {
            $feeId = BillingMath::cents($reservation->fee) > 0
                ? $this->billing->createCharge($reservation->property, 'Facility fee: '.$reservation->facility->name, $reservation->fee, $reservation->event_on->toDateString(), $administratorId)->id
                : null;
            $depositId = BillingMath::cents($reservation->deposit) > 0
                ? $this->billing->createCharge($reservation->property, 'Facility deposit: '.$reservation->facility->name, $reservation->deposit, $reservation->event_on->toDateString(), $administratorId)->id
                : null;
            $reservation->update([
                'approval_status' => 'Approved',
                'approved_by' => $administratorId,
                'fee_charge_id' => $feeId,
                'deposit_charge_id' => $depositId,
                'updated_by' => $administratorId,
            ]);
        });
    }

    public function declineReservation(FacilityReservation $reservation, int $administratorId): void
    {
        if ($reservation->approval_status !== 'Pending') {
            throw ValidationException::withMessages(['approval_status' => 'Only pending reservations can be declined.']);
        }

        $reservation->update(['approval_status' => 'Declined', 'approved_by' => $administratorId, 'updated_by' => $administratorId]);
    }

    public function refundDeposit(FacilityReservation $reservation, string $reference, int $treasurerId): void
    {
        if ($reservation->approval_status !== 'Approved' || $reservation->event_on->toDateString() >= now()->toDateString() || trim($reference) === '' || $reservation->refunded_on !== null) {
            throw ValidationException::withMessages(['refund_reference' => 'An approved, completed event and refund reference are required.']);
        }

        $deposit = $reservation->depositCharge;

        if ($deposit === null || in_array($deposit->status, ['Waived', 'Refunded'], true) || $this->accounts->chargeBalanceCents($deposit) > 0) {
            throw ValidationException::withMessages(['deposit' => 'The deposit must be fully paid before refunding.']);
        }

        DB::transaction(function () use ($reservation, $deposit, $reference, $treasurerId): void {
            FacilityReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $deposit->update(['status' => 'Refunded', 'updated_by' => $treasurerId]);
            $reservation->update([
                'payment_status' => 'Refunded',
                'refunded_on' => now()->toDateString(),
                'refunded_amount' => $reservation->deposit,
                'refund_reference' => trim($reference),
                'refunded_by' => $treasurerId,
                'updated_by' => $treasurerId,
            ]);
        });
    }
}
