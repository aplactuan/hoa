<?php

namespace App\Http\Controllers;

use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\AccountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PrintDocumentController extends Controller
{
    public function __construct(private AccountService $accounts) {}

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
}
