<?php

namespace App\Console\Commands;

use App\Models\Owner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('owners:backdate-start {--date=2025-01-01 : The start of ownership to move current owners back to} {--dry-run : Show the changes without saving}')]
#[Description('Move the start of ownership of current owners back to a date, such as when billing started')]
class BackdateOwnerStart extends Command
{
    public function handle(): int
    {
        $date = (string) $this->option('date');

        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Provide --date as YYYY-MM-DD.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        $owners = Owner::query()
            ->where('status', 'Current')
            ->whereDate('started_on', '>', $date)
            ->with('property')
            ->orderBy('property_id')
            ->get();

        foreach ($owners as $owner) {
            $label = "{$owner->property->code} · {$owner->name}";

            $previousOwnerEndedAfter = Owner::query()
                ->where('property_id', $owner->property_id)
                ->whereKeyNot($owner->id)
                ->whereDate('ended_on', '>', $date)
                ->exists();

            if ($previousOwnerEndedAfter) {
                $this->warn("Skipped {$label}: a previous owner held the property after {$date}.");
                $skipped++;

                continue;
            }

            $startedOn = $owner->started_on->toDateString();

            if (! $dryRun) {
                DB::transaction(fn () => $owner->update(['started_on' => $date]));
            }

            $this->line(($dryRun ? 'Would move ' : 'Moved ')."{$label}: {$startedOn} to {$date}");
            $updated++;
        }

        $this->info(($dryRun ? 'Would move' : 'Moved')." {$updated} owners; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
