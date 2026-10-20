<?php

namespace App\Jobs;

use App\Enums\RoleName;
use App\Services\BillingService;
use App\Services\OfficeAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyStatements implements ShouldQueue
{
    use Queueable;

    public function handle(BillingService $billing, OfficeAlerts $alerts): void
    {
        $month = now('Asia/Manila');
        $count = $billing->generatePeriod($month->year, $month->month);

        if ($count > 0) {
            $alerts->send([RoleName::Administrator, RoleName::Treasurer], 'Statements generated', $count.' statements are ready for review.', 'statements.index');
        }
    }
}
