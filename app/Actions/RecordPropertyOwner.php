<?php

namespace App\Actions;

use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPropertyOwner
{
    public function __construct(
        private PropertyRegistry $registry,
        private LinkOwnerUser $linkOwnerUser,
        private InviteOwnerUser $inviteOwnerUser,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Property $property, array $attributes, ?int $actorId = null): Owner
    {
        $email = filled($attributes['email'] ?? null)
            ? Str::lower(trim((string) $attributes['email']))
            : null;

        return DB::transaction(function () use ($property, $attributes, $email, $actorId): Owner {
            $user = $email !== null
                ? User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first()
                : null;
            $owner = $this->registry->transferOwnership($property, array_merge($attributes, ['email' => $email]), $actorId);

            if ($email === null) {
                return $owner;
            }

            if ($user !== null) {
                return $this->linkOwnerUser->handle($user, $owner, $actorId);
            }

            $this->inviteOwnerUser->handle($owner, $email, $actorId);

            return $owner->refresh();
        });
    }
}
