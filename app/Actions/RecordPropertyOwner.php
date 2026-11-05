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
        $email = Str::lower(trim((string) $attributes['email']));

        return DB::transaction(function () use ($property, $attributes, $email, $actorId): Owner {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            $owner = $this->registry->transferOwnership($property, array_merge($attributes, ['email' => $email]), $actorId);

            if ($user !== null) {
                return $this->linkOwnerUser->handle($user, $owner, $actorId);
            }

            $this->inviteOwnerUser->handle($owner, $email, $actorId);

            return $owner->refresh();
        });
    }
}
