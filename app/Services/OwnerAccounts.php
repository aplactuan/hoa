<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Groups properties by the person who currently owns them.
 *
 * An owner with a portal login is identified by their user account, so every property they
 * currently own is billed as one account. An owner without a login, or a property without a
 * current owner, is its own group. The lowest-id property in a group is the billing property:
 * the flat monthly fee is charged there once.
 */
class OwnerAccounts
{
    /**
     * @return array<string, list<int>> property ids keyed by group, each list in ascending id order
     */
    public function allGroups(): array
    {
        $groups = [];

        $this->propertiesWithOwnerUser()->each(function (Property $property) use (&$groups): void {
            $groups[$this->keyFor($property->id, $property->getAttribute('owner_user_id'))][] = $property->id;
        });

        return $groups;
    }

    /**
     * @return Collection<int, Property>
     */
    public function groupProperties(Property $property): Collection
    {
        $userId = $this->ownerUserId($property);

        if ($userId === null) {
            return Property::query()->whereKey($property->id)->get();
        }

        return Property::query()
            ->whereHas('currentOwner', fn (Builder $query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->get();
    }

    public function billingProperty(Property $property): Property
    {
        return $this->groupProperties($property)->firstOrFail();
    }

    /**
     * @return Collection<int, Property>
     */
    public function propertiesForUser(User $user): Collection
    {
        return $user->currentOwnedProperties()->orderBy('id')->get();
    }

    private function ownerUserId(Property $property): ?int
    {
        $currentOwnerId = Property::query()->whereKey($property->id)->value('current_owner_id');
        $userId = $currentOwnerId === null ? null : Owner::query()->whereKey($currentOwnerId)->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    private function keyFor(int $propertyId, mixed $userId): string
    {
        return $userId === null ? 'property:'.$propertyId : 'user:'.$userId;
    }

    /**
     * @return Collection<int, Property>
     */
    private function propertiesWithOwnerUser(): Collection
    {
        return Property::query()
            ->leftJoin('owners', function ($join): void {
                $join->on('owners.id', '=', 'properties.current_owner_id')->whereNull('owners.deleted_at');
            })
            ->orderBy('properties.id')
            ->get(['properties.id', 'owners.user_id as owner_user_id']);
    }
}
