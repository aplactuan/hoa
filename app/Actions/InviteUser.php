<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteUser
{
    public function handle(string $name, string $email, RoleName $role): User
    {
        return DB::transaction(function () use ($name, $email, $role): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(32),
            ]);

            $user->assignRole($role);

            $token = Password::broker()->createToken($user);
            $user->notify(new SetPassword($token));

            return $user;
        });
    }
}
