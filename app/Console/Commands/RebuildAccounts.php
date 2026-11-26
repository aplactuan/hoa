<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\BillingService;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('billing:rebuild-accounts
    {--from=2025-01 : The first month (YYYY-MM) to bill and reprice}
    {--monthly-fee= : Save this monthly fee as the rate effective from --from before rebuilding}
    {--dry-run : Show what would change without saving}
    {--force : Run without asking for confirmation in production}')]
#[Description('Rebuild every owner account from the current membership fee, monthly fee and penalty rules')]
class RebuildAccounts extends Command
{
    use ConfirmableTrait;

    public function handle(BillingService $billing): int
    {
        $validator = Validator::make($this->options(), [
            'from' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'monthly-fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $from = CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('from'));
        $monthlyFee = $this->option('monthly-fee');

        if (BillingMath::cents(Setting::current()->membership_fee) <= 0) {
            $this->warn('The membership fee is ₱0.00, so no membership fees will be charged or repriced.');
        }

        $this->info("Rebuilding owner accounts from {$from->format('M Y')}".($dryRun ? ' (dry run)' : '').'...');
        $bar = $this->output->createProgressBar();
        $rebuild = fn (): array => $billing->rebuildAccounts($from, $monthlyFee === null ? null : (string) $monthlyFee, progress: function (int $done, int $total) use ($bar): void {
            $bar->setMaxSteps($total);
            $bar->setProgress($done);
        });

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $summary = $rebuild();
            } finally {
                DB::rollBack();
            }
        } else {
            $summary = $rebuild();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', $dryRun ? 'Would change' : 'Changed'], [
            ['Statements created', $summary['statements_created']],
            ['Statements repriced', $summary['statements_repriced']],
            ['Membership fees created', $summary['membership_fees_created']],
            ['Membership fees updated', $summary['membership_fees_updated']],
            ['Accounts recalculated', $summary['accounts_recalculated']],
        ]);

        if ($dryRun) {
            $this->info('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }
}
