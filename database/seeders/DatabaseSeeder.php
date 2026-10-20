<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            $this->call([
                AccessSeeder::class,
                MasterDataSeeder::class,
            ]);

            if (! app()->environment('local')) {
                return;
            }

            $accounts = [
                ['name' => 'Ada Administrator', 'email' => 'admin@greenmeadows.test', 'role' => RoleName::Administrator],
                ['name' => 'Terry Treasurer', 'email' => 'treasurer@greenmeadows.test', 'role' => RoleName::Treasurer],
                ['name' => 'Sam Staff', 'email' => 'staff@greenmeadows.test', 'role' => RoleName::OfficeStaff],
                ['name' => 'Gio Guard', 'email' => 'guard@greenmeadows.test', 'role' => RoleName::SecurityGuard],
            ];

            foreach ($accounts as $account) {
                $user = User::query()->firstOrNew(['email' => $account['email']]);
                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ])->save();
                $user->assignRole($account['role']);
            }
        });
    }
}
