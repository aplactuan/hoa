<?php

namespace App\Services;

use App\Enums\OwnerChangeStatus;
use App\Enums\OwnerChangeType;
use App\Enums\RoleName;
use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\Owner;
use App\Models\OwnerChangeRequest;
use App\Models\Property;
use App\Models\Resident;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\OfficeAttention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerChangeRequestService
{
    public function __construct(
        private PropertyRegistry $registry,
        private DisplayCode $codes,
        private OfficeAlerts $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(
        User $requester,
        Property $property,
        OwnerChangeType $type,
        array $payload,
        ?Model $target = null,
    ): OwnerChangeRequest {
        if (! $requester->ownsCurrentProperty($property)) {
            abort(404);
        }

        $this->ensureTargetBelongsToProperty($target, $property);

        if ($type === OwnerChangeType::OwnerContact
            && (! $target instanceof Owner || $target->user_id !== $requester->id || $target->status !== 'Current')) {
            abort(404);
        }

        if ($target !== null && OwnerChangeRequest::query()
            ->where('requested_by', $requester->id)
            ->where('type', $type->value)
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->where('status', OwnerChangeStatus::Pending->value)
            ->exists()) {
            throw ValidationException::withMessages(['request' => 'A pending change already exists for this record.']);
        }

        $changeRequest = DB::transaction(fn (): OwnerChangeRequest => OwnerChangeRequest::query()->create([
            'property_id' => $property->id,
            'requested_by' => $requester->id,
            'type' => $type,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'payload' => $this->allowedPayload($type, $payload),
            'status' => OwnerChangeStatus::Pending,
        ]));

        $this->alerts->send(
            [RoleName::Administrator, RoleName::OfficeStaff],
            'Owner change awaiting review',
            $changeRequest->type->label().' for '.$property->code.'.',
            'owner-changes.index',
        );

        return $changeRequest;
    }

    public function approve(OwnerChangeRequest $changeRequest, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($changeRequest, $reviewer, $note): void {
            $changeRequest = OwnerChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $this->ensurePending($changeRequest);

            $requester = User::query()->find($changeRequest->requested_by);

            if (! $requester?->ownsCurrentProperty($changeRequest->property_id)) {
                throw ValidationException::withMessages(['request' => 'The requester is no longer a current owner of this property.']);
            }

            $this->apply($changeRequest, $reviewer->id);
            $changeRequest->update([
                'status' => OwnerChangeStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'decision_note' => $note,
                'reviewed_at' => now(),
            ]);
        });

        $changeRequest->requester?->notify(new OfficeAttention(
            'Change request approved',
            $changeRequest->type->label().' was approved.',
            'owner.changes',
        ));
    }

    public function decline(OwnerChangeRequest $changeRequest, User $reviewer, string $note): void
    {
        DB::transaction(function () use ($changeRequest, $reviewer, $note): void {
            $changeRequest = OwnerChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);
            $this->ensurePending($changeRequest);
            $changeRequest->update([
                'status' => OwnerChangeStatus::Declined,
                'reviewed_by' => $reviewer->id,
                'decision_note' => $note,
                'reviewed_at' => now(),
            ]);
        });

        $changeRequest->requester?->notify(new OfficeAttention(
            'Change request declined',
            $changeRequest->type->label().' was declined.',
            'owner.changes',
        ));
    }

    public function cancel(OwnerChangeRequest $changeRequest, User $requester): void
    {
        DB::transaction(function () use ($changeRequest, $requester): void {
            $changeRequest = OwnerChangeRequest::query()->lockForUpdate()->findOrFail($changeRequest->id);

            if ($changeRequest->requested_by !== $requester->id || ! $requester->ownsCurrentProperty($changeRequest->property_id)) {
                abort(404);
            }

            $this->ensurePending($changeRequest);
            $changeRequest->update(['status' => OwnerChangeStatus::Cancelled]);
        });
    }

    private function apply(OwnerChangeRequest $changeRequest, int $reviewerId): void
    {
        $property = Property::query()->lockForUpdate()->findOrFail($changeRequest->property_id);
        $payload = $changeRequest->payload;
        $target = $changeRequest->target;
        $this->ensureTargetBelongsToProperty($target, $property);

        match ($changeRequest->type) {
            OwnerChangeType::OwnerContact => $this->updateOwner($target, $payload, $reviewerId),
            OwnerChangeType::ResidentMoveIn => $this->registry->moveIn($property, $payload, $reviewerId),
            OwnerChangeType::ResidentUpdate => $this->updateRecord($target, $payload, $reviewerId),
            OwnerChangeType::ResidentMoveOut => $this->moveOutResident($target, $payload, $reviewerId),
            OwnerChangeType::FamilyAdd => $this->addFamilyMember($property, $payload, $reviewerId),
            OwnerChangeType::FamilyUpdate => $this->updateRecord($target, $payload, $reviewerId),
            OwnerChangeType::FamilyRemove => $this->deactivateRecord($target, 'Moved Out', $reviewerId),
            OwnerChangeType::HouseStaffAdd => $this->addHouseStaff($property, $payload, $reviewerId),
            OwnerChangeType::HouseStaffUpdate => $this->updateRecord($target, $payload, $reviewerId),
            OwnerChangeType::HouseStaffDeactivate => $this->deactivateRecord($target, 'Inactive', $reviewerId),
            OwnerChangeType::VehicleAdd => $this->registry->registerVehicle($property, $payload, $reviewerId),
            OwnerChangeType::VehicleUpdate => $this->updateVehicle($target, $payload, $reviewerId),
            OwnerChangeType::VehicleDeactivate => $this->deactivateRecord($target, 'Inactive', $reviewerId),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function allowedPayload(OwnerChangeType $type, array $payload): array
    {
        $keys = match ($type) {
            OwnerChangeType::OwnerContact => ['phone', 'email', 'mailing_address'],
            OwnerChangeType::ResidentMoveIn => ['name', 'phone', 'email', 'occupancy_type', 'moved_in_on', 'emergency_contact_name', 'emergency_contact_phone'],
            OwnerChangeType::ResidentUpdate => ['name', 'phone', 'email', 'occupancy_type', 'emergency_contact_name', 'emergency_contact_phone'],
            OwnerChangeType::ResidentMoveOut => ['moved_out_on'],
            OwnerChangeType::FamilyAdd, OwnerChangeType::FamilyUpdate => ['name', 'relationship', 'phone', 'email', 'birth_date', 'occupation', 'is_emergency_contact'],
            OwnerChangeType::FamilyRemove => ['moved_out_on'],
            OwnerChangeType::HouseStaffAdd, OwnerChangeType::HouseStaffUpdate => ['name', 'role', 'phone', 'id_type', 'id_reference', 'pass_no', 'pass_expires_on', 'started_on'],
            OwnerChangeType::HouseStaffDeactivate => ['ended_on'],
            OwnerChangeType::VehicleAdd, OwnerChangeType::VehicleUpdate => ['driver_name', 'type', 'make', 'model', 'color', 'plate_no', 'parking'],
            OwnerChangeType::VehicleDeactivate => [],
        };

        return Arr::only($payload, $keys);
    }

    private function ensurePending(OwnerChangeRequest $changeRequest): void
    {
        if ($changeRequest->status !== OwnerChangeStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'Only a pending request can be reviewed.']);
        }
    }

    private function ensureTargetBelongsToProperty(?Model $target, Property $property): void
    {
        if ($target !== null && (int) $target->getAttribute('property_id') !== $property->id) {
            abort(404);
        }
    }

    /** @param array<string, mixed> $payload */
    private function updateOwner(?Model $target, array $payload, int $reviewerId): void
    {
        if (! $target instanceof Owner || $target->status !== 'Current') {
            throw ValidationException::withMessages(['request' => 'The linked owner record is no longer current.']);
        }

        $target->update(array_merge($payload, ['updated_by' => $reviewerId]));
    }

    /** @param array<string, mixed> $payload */
    private function updateRecord(?Model $target, array $payload, int $reviewerId): void
    {
        if (! $target instanceof Resident && ! $target instanceof FamilyMember && ! $target instanceof HouseStaff) {
            throw ValidationException::withMessages(['request' => 'The requested record is no longer available.']);
        }

        $target->update(array_merge($payload, ['updated_by' => $reviewerId]));
    }

    /** @param array<string, mixed> $payload */
    private function moveOutResident(?Model $target, array $payload, int $reviewerId): void
    {
        if (! $target instanceof Resident) {
            throw ValidationException::withMessages(['request' => 'The resident is no longer available.']);
        }

        $this->registry->moveOut($target, (string) $payload['moved_out_on'], $reviewerId);
    }

    /** @param array<string, mixed> $payload */
    private function addFamilyMember(Property $property, array $payload, int $reviewerId): void
    {
        if ($property->current_resident_id === null) {
            throw ValidationException::withMessages(['request' => 'A current resident is required before adding a family member.']);
        }

        $property->familyMembers()->create(array_merge($payload, [
            'code' => $this->codes->next('family_member'),
            'resident_id' => $property->current_resident_id,
            'status' => 'Active',
            'created_by' => $reviewerId,
            'updated_by' => $reviewerId,
        ]));
    }

    /** @param array<string, mixed> $payload */
    private function addHouseStaff(Property $property, array $payload, int $reviewerId): void
    {
        $property->houseStaff()->create(array_merge($payload, [
            'code' => $this->codes->next('house_staff'),
            'resident_id' => $property->current_resident_id,
            'status' => 'Active',
            'created_by' => $reviewerId,
            'updated_by' => $reviewerId,
        ]));
    }

    private function deactivateRecord(?Model $target, string $status, int $reviewerId): void
    {
        if (! $target instanceof FamilyMember && ! $target instanceof HouseStaff && ! $target instanceof Vehicle) {
            throw ValidationException::withMessages(['request' => 'The requested record is no longer available.']);
        }

        $dates = $target instanceof FamilyMember
            ? ['moved_out_on' => now()->toDateString()]
            : ($target instanceof HouseStaff ? ['ended_on' => now()->toDateString()] : []);

        $target->update(array_merge($dates, ['status' => $status, 'updated_by' => $reviewerId]));
    }

    /** @param array<string, mixed> $payload */
    private function updateVehicle(?Model $target, array $payload, int $reviewerId): void
    {
        if (! $target instanceof Vehicle) {
            throw ValidationException::withMessages(['request' => 'The vehicle is no longer available.']);
        }

        if (isset($payload['plate_no'])) {
            $normalizedPlate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $payload['plate_no']) ?? '');
            $duplicate = Vehicle::query()
                ->whereKeyNot($target->id)
                ->where('normalized_plate', $normalizedPlate)
                ->where('status', 'Active')
                ->exists();

            if ($normalizedPlate === '' || $duplicate) {
                throw ValidationException::withMessages(['request' => 'The requested plate number is invalid or already active.']);
            }

            $payload['normalized_plate'] = $normalizedPlate;
        }

        $target->update(array_merge($payload, ['updated_by' => $reviewerId]));
    }
}
