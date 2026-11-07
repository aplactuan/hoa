<?php

namespace App\Console\Commands;

use App\Actions\LinkOwnerUser;
use App\Enums\RoleName;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('owners:create-residential-users {--password= : Initial password for newly created accounts} {--dry-run : Show accounts and links without saving}')]
#[Description('Create active owner accounts and link their current residential properties')]
class CreateResidentialOwnerUsers extends Command
{
    public function handle(LinkOwnerUser $linkOwnerUser): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $password = $this->option('password');

        if (! $dryRun && (! is_string($password) || $password === '')) {
            $this->error('Provide an initial password with --password.');

            return self::FAILURE;
        }

        $owners = Owner::query()
            ->where('status', 'Current')
            ->whereHas('property.propertyCategory', function (Builder $query): void {
                $query->where('name', 'Residential');
            })
            ->with('property')
            ->get()
            ->groupBy(fn (Owner $owner): string => Str::lower(trim($owner->first_name).' '.trim((string) $owner->last_name)));

        $emailGroups = $owners->keys()->groupBy(function (string $name) use ($owners): string {
            return $this->emailFor($owners->get($name)->first()) ?? '';
        });

        $created = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($owners as $name => $records) {
            $owner = $records->first();
            $email = $this->emailFor($owner);
            $codes = $records->pluck('property.code')->implode(', ');

            if ($email === null || $emailGroups->get($email)->count() > 1) {
                $this->warn("Skipped {$owner->name} ({$codes}): missing name parts or generated email collision.");
                $skipped++;

                continue;
            }

            $user = User::query()->where('email', $email)->first();
            $linkedUserIds = $records->pluck('user_id')->filter()->unique();

            if ($linkedUserIds->isNotEmpty() && ($user === null || $linkedUserIds->count() !== 1 || $linkedUserIds->first() !== $user->id)) {
                $this->warn("Skipped {$owner->name} ({$codes}): an owner record belongs to another account.");
                $skipped++;

                continue;
            }

            if ($user !== null && (! $user->hasRole(RoleName::Owner)
                || Str::lower(trim($user->name)) !== $name
                || $user->currentOwnerRecords()->where(function (Builder $query) use ($name): void {
                    $query->whereRaw('LOWER(TRIM(name)) != ?', [$name])
                        ->orWhereHas('property', function (Builder $property): void {
                            $property->whereDoesntHave('propertyCategory', fn (Builder $category): Builder => $category->where('name', 'Residential'));
                        });
                })->exists()
                || $linkedUserIds->isEmpty())) {
                $this->warn("Skipped {$owner->name} ({$codes}): generated email already belongs to another account.");
                $skipped++;

                continue;
            }

            $newLinks = $records->whereNull('user_id');

            if ($newLinks->isEmpty()) {
                $this->line("Already linked {$email}: {$codes}");

                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($owner, $email, $password, $user, $newLinks, $linkOwnerUser): void {
                    if ($user === null) {
                        $user = User::query()->create([
                            'name' => Owner::displayName($owner->first_name, $owner->last_name),
                            'email' => $email,
                            'password' => $password,
                        ]);
                        $user->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
                        $user->assignRole(RoleName::Owner);
                    }

                    foreach ($newLinks as $record) {
                        $linkOwnerUser->handle($user, $record);
                    }
                });
            }

            $created += (int) ($user === null);
            $linked += $newLinks->count();
            $this->info(($dryRun ? 'Would link ' : 'Linked ')."{$email}: {$codes}");
        }

        $this->info("Accounts: {$created}; property links: {$linked}; skipped owners: {$skipped}".($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }

    private function emailFor(?Owner $owner): ?string
    {
        if ($owner === null) {
            return null;
        }

        $firstName = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($owner->first_name)));
        $lastName = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii((string) $owner->last_name)));

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        return Str::substr($firstName, 0, 4).'_'.Str::substr($lastName, 0, 4).'@agweb.io';
    }
}
