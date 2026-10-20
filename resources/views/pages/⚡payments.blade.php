<?php

use App\Models\Payment;
use App\Models\Property;
use App\Services\BillingService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payments')] class extends Component
{
    use WithPagination;

    public string $propertyId = '';
    public string $amount = '';
    public string $paidOn = '';
    public string $method = 'Cash';
    public string $referenceNo = '';
    public string $remarks = '';
    public string $voidReason = '';
    public ?int $selectedPaymentId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);
        $this->paidOn = now()->toDateString();
    }

    #[Computed]
    public function payments()
    {
        return Payment::query()->with('property')->latest('paid_on')->paginate(20);
    }

    #[Computed]
    public function properties()
    {
        return Property::query()->orderBy('block')->orderBy('lot')->get(['id', 'code', 'block', 'lot']);
    }

    public function save(BillingService $billing): void
    {
        $this->authorize('create', Payment::class);
        $data = $this->validate([
            'propertyId' => ['required', 'integer', 'exists:properties,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paidOn' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(['Cash', 'Check', 'GCash', 'Maya', 'Bank Transfer'])],
            'referenceNo' => [$this->method === 'Cash' ? 'nullable' : 'required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $property = Property::query()->findOrFail($data['propertyId']);
        $billing->recordPayment($property, [
            'amount' => $data['amount'],
            'paid_on' => $data['paidOn'],
            'method' => $data['method'],
            'reference_no' => $data['referenceNo'] ?: null,
            'remarks' => $data['remarks'] ?: null,
        ], auth()->id());
        $this->reset(['propertyId', 'amount', 'referenceNo', 'remarks']);
        unset($this->payments);
        Flux::toast(variant: 'success', text: __('Payment recorded.'));
    }

    public function requestVoid(BillingService $billing): void
    {
        $payment = Payment::query()->findOrFail($this->selectedPaymentId);
        $this->authorize('requestVoid', $payment);
        $this->validate(['voidReason' => ['required', 'string', 'max:2000']]);
        $billing->requestVoid($payment, $this->voidReason, auth()->id());
        $this->reset(['selectedPaymentId', 'voidReason']);
        unset($this->payments);
        Flux::toast(variant: 'success', text: __('Void requested.'));
    }

    public function approveVoid(int $id, BillingService $billing): void
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('approveVoid', $payment);
        $billing->approveVoid($payment, auth()->id());
        unset($this->payments);
        Flux::toast(variant: 'success', text: __('Payment voided.'));
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header title="Payments" subtitle="Record office payments and official receipt numbers." />
        @can('create', App\Models\Payment::class)
            <flux:card><form wire:submit="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><label class="text-sm">Property<select wire:model="propertyId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select property</option>@foreach ($this->properties as $property)<option value="{{ $property->id }}">{{ $property->code }} · Block {{ $property->block }} Lot {{ $property->lot }}</option>@endforeach</select></label><flux:input label="Amount" type="number" step="0.01" wire:model="amount" /><flux:input label="Payment date" type="date" wire:model="paidOn" /><label class="text-sm">Method<select wire:model.live="method" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Cash', 'Check', 'GCash', 'Maya', 'Bank Transfer'] as $option)<option>{{ $option }}</option>@endforeach</select></label><flux:input label="Reference number" wire:model="referenceNo" /><flux:input label="Remarks" wire:model="remarks" /><flux:button type="submit" variant="primary">Record payment</flux:button></form></flux:card>
        @endcan
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-3">OR</th><th class="p-3">Property</th><th class="p-3">Date</th><th class="p-3">Amount</th><th class="p-3">Method</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody>
            @forelse ($this->payments as $payment)
                <tr wire:key="payment-{{ $payment->id }}" class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3"><a href="{{ route('receipts.print', $payment) }}" target="_blank" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $payment->or_no }}</a></td><td class="p-3">{{ $payment->property->code }}</td><td class="p-3">{{ $payment->paid_on->toDateString() }}</td><td class="p-3">₱{{ $payment->amount }}</td><td class="p-3">{{ $payment->method }}</td><td class="p-3"><x-status-pill :label="$payment->status" /></td><td class="p-3">@can('approveVoid', $payment) @if ($payment->status === 'Void Requested')<flux:button size="sm" wire:click="approveVoid({{ $payment->id }})">Approve void</flux:button>@endif @endcan</td></tr>
            @empty <tr><td colspan="7" class="p-6 text-center">No payments yet.</td></tr> @endforelse
        </tbody></table></div>
        {{ $this->payments->links() }}
        @can('create', App\Models\Payment::class)
            <flux:card><form wire:submit="requestVoid" class="flex flex-wrap items-end gap-3"><label class="text-sm">Payment<select wire:model="selectedPaymentId" class="block rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->payments as $payment) @if ($payment->status === 'Posted')<option value="{{ $payment->id }}">{{ $payment->or_no }}</option>@endif @endforeach</select></label><flux:input label="Reason" wire:model="voidReason" /><flux:button type="submit">Request void</flux:button></form></flux:card>
        @endcan
    </div>
</div>
