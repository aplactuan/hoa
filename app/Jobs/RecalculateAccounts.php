<?php

namespace App\Jobs;

use App\Services\BillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings every homeowner account up to date: late penalties accrue on the day after each due date and
 * penalty amnesties lapse on a set date, so balances change even when nobody records anything.
 */
class RecalculateAccounts implements ShouldQueue
{
    use Queueable;

    public function handle(BillingService $billing): void
    {
        $billing->recalculateAll();
    }
}
