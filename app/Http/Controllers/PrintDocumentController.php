<?php

namespace App\Http\Controllers;

use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountService;
use App\Services\OwnerAccounts;
use App\Services\StatementOfAccount;
use App\Support\BillingMath;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintDocumentController extends Controller
{
    public function __construct(private AccountService $accounts, private StatementOfAccount $statementOfAccount, private OwnerAccounts $owners) {}

    public function statement(DuesStatement $duesStatement): Response
    {
        Gate::authorize('view', $duesStatement);
        $duesStatement->load(['property.currentOwner', 'billingPeriod']);

        return Pdf::loadView('documents.statement', [
            'statement' => $duesStatement,
            'setting' => Setting::current(),
            'account' => $this->accounts->summary($duesStatement->property),
        ])->setPaper('a4')->stream($duesStatement->code.'.pdf');
    }

    public function receipt(Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        $payment->load(['property.currentOwner', 'allocations.allocatable']);

        return Pdf::loadView('documents.receipt', ['payment' => $payment, 'setting' => Setting::current()])
            ->setPaper('a4')->stream($payment->or_no.'.pdf');
    }

    public function ownerStatementOfAccount(Request $request): Response
    {
        return $this->statementOfAccountPdf($this->ownerProperties($request), $this->asOf($request));
    }

    public function ownerStatementOfAccountCsv(Request $request): StreamedResponse
    {
        return $this->statementOfAccountCsv($this->ownerProperties($request), $this->asOf($request));
    }

    public function propertyStatementOfAccount(Request $request, Property $property): Response
    {
        return $this->statementOfAccountPdf($this->owners->groupProperties($property), $this->asOf($request));
    }

    public function propertyStatementOfAccountCsv(Request $request, Property $property): StreamedResponse
    {
        return $this->statementOfAccountCsv($this->owners->groupProperties($property), $this->asOf($request));
    }

    /**
     * @return Collection<int, Property>
     */
    private function ownerProperties(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();
        $properties = $this->owners->propertiesForUser($user);
        abort_if($properties->isEmpty(), 404);

        return $properties;
    }

    private function asOf(Request $request): CarbonInterface
    {
        $validated = $request->validate(['as_of' => ['nullable', 'date', 'before_or_equal:today']]);

        return isset($validated['as_of']) ? Carbon::parse($validated['as_of']) : now();
    }

    /**
     * @param  Collection<int, Property>  $properties
     */
    private function statementOfAccountPdf(Collection $properties, CarbonInterface $asOf): Response
    {
        return Pdf::loadView('documents.statement-of-account', [
            'account' => $this->statementOfAccount->forProperties($properties, $asOf),
            'setting' => Setting::current(),
        ])->setPaper('a4')->stream('SOA-'.$asOf->toDateString().'.pdf');
    }

    /**
     * The statement as a spreadsheet: owner details, the transaction ledger, then the totals.
     *
     * @param  Collection<int, Property>  $properties
     */
    private function statementOfAccountCsv(Collection $properties, CarbonInterface $asOf): StreamedResponse
    {
        $account = $this->statementOfAccount->forProperties($properties, $asOf);
        $decimal = fn (int $cents): string => $cents === 0 ? '' : BillingMath::decimal($cents);

        return response()->streamDownload(function () use ($account, $decimal): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Statement of Account']);
            fputcsv($out, ['Homeowner', $account['owner_name']]);

            foreach ($account['properties'] as $property) {
                fputcsv($out, ['Property', $property['code'].' · '.$property['block_lot'], $property['address'] ?? '']);
            }

            fputcsv($out, ['Member since', $account['member_since'] ?? '']);
            fputcsv($out, ['Statement as of', $account['as_of']]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Description', 'Reference', 'Property', 'Charges', 'Payments', 'Running balance']);

            foreach ($account['lines'] as $line) {
                fputcsv($out, [$line['date'], $line['description'], $line['reference'], $line['property'], $decimal($line['debit_cents']), $decimal($line['credit_cents']), BillingMath::decimal($line['balance_cents'])]);
            }

            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', 'Total billed', BillingMath::decimal($account['billed_cents'])]);
            fputcsv($out, ['', '', '', '', 'Penalties', BillingMath::decimal($account['billed_penalties_cents'])]);
            fputcsv($out, ['', '', '', '', 'Discount', BillingMath::decimal($account['discount_cents'])]);
            fputcsv($out, ['', '', '', '', 'Total paid', BillingMath::decimal($account['paid_cents'])]);
            fputcsv($out, ['', '', '', '', $account['balance_cents'] < 0 ? 'Credit balance' : 'Balance due', BillingMath::decimal(abs($account['balance_cents']))]);
            fclose($out);
        }, 'SOA-'.$asOf->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
