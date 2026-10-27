<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Models\Owner;
use App\Models\User;
use App\Notifications\SetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteOwnerUser
{
    public function __construct(private LinkOwnerUser $linkOwnerUser) {}

    public function handle(Owner $owner, string $email, ?int $actorId = null): User
    {
        $user = DB::transaction(function () use ($owner, $email, $actorId): User {
            $user = User::query()->create([
                'name' => $owner->name,
                'email' => $email,
                'password' => Str::password(32),
            ]);

            $user->assignRole(RoleName::Owner);
            $this->linkOwnerUser->handle($user, $owner, $actorId);

            return $user;
        });

        $token = Password::broker()->createToken($user);
        $user->notify(new SetPassword($token));

        return $user;
    }
}
