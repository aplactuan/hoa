<?php

namespace App\Actions;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkOwnerUser
{
    public function handle(User $user, Owner $owner, ?int $actorId = null): Owner
    {
        return DB::transaction(function () use ($user, $owner, $actorId): Owner {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $owner = Owner::query()->lockForUpdate()->findOrFail($owner->id);

            if ($owner->status !== 'Current') {
                throw ValidationException::withMessages(['ownerId' => 'Only a current owner can be linked to an account.']);
            }

            if ($owner->user_id !== null && $owner->user_id !== $user->id) {
                throw ValidationException::withMessages(['ownerId' => 'This owner record is already linked to another account.']);
            }

            $owner->update([
                'user_id' => $user->id,
                'is_registered' => true,
                'verified_on' => $owner->verified_on ?? now()->toDateString(),
                'updated_by' => $actorId,
            ]);

            return $owner;
        });
    }
}
