<?php

use App\Livewire\OwnerPortalComponent;
use App\Models\RequestCategory;
use App\Models\ServiceCase;
use App\Models\TransactionType;
use App\Services\OfficeService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My Requests')] class extends OwnerPortalComponent
{
    public string $requestCategoryId = '';
    public string $description = '';
    public string $priority = 'Medium';
    public string $transactionTypeId = '';
    public string $caseNote = '';

    public function mount(): void
    {
        $this->initializeOwnedProperty();
    }

    #[Computed]
    public function cases()
    {
        return $this->selectedProperty
            ? $this->selectedProperty->cases()->with(['category', 'updates.user'])->latest()->get()
            : collect();
    }

    #[Computed]
    public function transactions()
    {
        return $this->selectedProperty
            ? $this->selectedProperty->transactions()->with('transactionType')->latest()->get()
            : collect();
    }

    #[Computed]
    public function requestCategories()
    {
        return RequestCategory::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function transactionTypes()
    {
        return TransactionType::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function submitCase(OfficeService $office): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'requestCategoryId' => ['required', 'integer', Rule::exists('request_categories', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
        ]);
        $office->logCase(
            $this->selectedProperty,
            RequestCategory::query()->findOrFail((int) $data['requestCategoryId']),
            $this->ownerUser()->name,
            $data['description'],
            $data['priority'],
            $this->ownerUser()->id,
        );
        $this->reset(['requestCategoryId', 'description']);
        unset($this->cases);
        Flux::toast(variant: 'success', text: __('Request submitted.'));
    }

    public function requestService(OfficeService $office): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'transactionTypeId' => ['required', 'integer', Rule::exists('transaction_types', 'id')->where('is_active', true)],
        ]);
        $office->createTransaction(
            $this->selectedProperty,
            TransactionType::query()->findOrFail((int) $data['transactionTypeId']),
            $this->ownerUser()->name,
            $this->ownerUser()->id,
        );
        $this->reset('transactionTypeId');
        unset($this->transactions);
        Flux::toast(variant: 'success', text: __('Service requested.'));
    }

    public function addCaseUpdate(int $id, OfficeService $office): void
    {
        abort_unless($this->selectedProperty, 404);
        $this->validate(['caseNote' => ['required', 'string', 'max:5000']]);
        $case = $this->selectedProperty->cases()->findOrFail($id);
        $office->addCaseUpdate($case, $this->caseNote, $this->ownerUser()->id);
        $this->reset('caseNote');
        unset($this->cases);
        Flux::toast(variant: 'success', text: __('Update added.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Requests')" :subtitle="__('File a concern, request an HOA service, and follow its progress.')" />
    @if (! $this->selectedProperty)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}" />
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />
        <div class="grid gap-6 xl:grid-cols-2">
            <flux:card><form wire:submit="submitCase" class="space-y-3"><flux:heading size="lg">{{ __('Request or complaint') }}</flux:heading><flux:select label="{{ __('Category') }}" wire:model="requestCategoryId" placeholder="{{ __('Choose a category') }}">@foreach ($this->requestCategories as $category)<flux:select.option :value="$category->id" wire:key="owner-category-{{ $category->id }}">{{ $category->name }}</flux:select.option>@endforeach</flux:select><flux:select label="{{ __('Priority') }}" wire:model="priority">@foreach (['Low', 'Medium', 'High', 'Urgent'] as $option)<flux:select.option :value="$option">{{ $option }}</flux:select.option>@endforeach</flux:select><flux:textarea label="{{ __('Details') }}" wire:model="description" rows="5" /><flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button></form></flux:card>
            <flux:card><form wire:submit="requestService" class="space-y-3"><flux:heading size="lg">{{ __('HOA service') }}</flux:heading><flux:text>{{ __('Request a document, sticker, or other configured service. Any fee appears on My Account.') }}</flux:text><flux:select label="{{ __('Service') }}" wire:model="transactionTypeId" placeholder="{{ __('Choose a service') }}">@foreach ($this->transactionTypes as $type)<flux:select.option :value="$type->id" wire:key="owner-transaction-type-{{ $type->id }}">{{ $type->name }} · ₱{{ $type->default_fee }}</flux:select.option>@endforeach</flux:select><flux:button type="submit" variant="primary">{{ __('Request service') }}</flux:button></form></flux:card>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Requests and complaints') }}</flux:heading>@forelse ($this->cases as $case)<div wire:key="owner-case-{{ $case->id }}" class="space-y-2 border-t border-zinc-200 py-3 dark:border-zinc-700"><div class="flex justify-between gap-3"><strong>{{ $case->code }} · {{ $case->category?->name }}</strong><x-status-pill :label="$case->status" /></div><flux:text>{{ $case->description }}</flux:text>@foreach ($case->updates as $update)<div class="rounded-lg bg-zinc-50 p-2 text-sm dark:bg-zinc-800">{{ $update->note }}<div class="text-xs text-zinc-500">{{ $update->user?->name }} · {{ $update->created_at->diffForHumans() }}</div></div>@endforeach @if (! in_array($case->status, ['Closed', 'Resolved'], true))<div class="flex gap-2"><flux:input wire:model="caseNote" placeholder="{{ __('Add information') }}" /><flux:button wire:click="addCaseUpdate({{ $case->id }})">{{ __('Add') }}</flux:button></div>@endif</div>@empty<flux:text>{{ __('No requests yet.') }}</flux:text>@endforelse</flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Service requests') }}</flux:heading>@forelse ($this->transactions as $transaction)<div wire:key="owner-service-{{ $transaction->id }}" class="flex justify-between gap-3 border-t border-zinc-200 py-3 dark:border-zinc-700"><span>{{ $transaction->code }} · {{ $transaction->transactionType->name }} · ₱{{ $transaction->amount }}</span><x-status-pill :label="$transaction->status" /></div>@empty<flux:text>{{ __('No service requests yet.') }}</flux:text>@endforelse</flux:card>
        </div>
    @endif
</div>