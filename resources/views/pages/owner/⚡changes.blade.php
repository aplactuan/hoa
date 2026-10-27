<?php

use App\Enums\OwnerChangeStatus;
use App\Models\OwnerChangeRequest;
use App\Services\OwnerChangeRequestService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Change Requests')] class extends Component
{
    #[Computed]
    public function requests()
    {
        return OwnerChangeRequest::query()
            ->with(['property', 'reviewer'])
            ->where('requested_by', auth()->id())
            ->whereIn('property_id', auth()->user()->currentOwnedProperties()->select('properties.id'))
            ->latest()
            ->get();
    }

    public function cancel(int $id, OwnerChangeRequestService $changes): void
    {
        $changeRequest = OwnerChangeRequest::query()->findOrFail($id);
        $this->authorize('cancel', $changeRequest);
        $changes->cancel($changeRequest, auth()->user());
        unset($this->requests);
        Flux::toast(variant: 'success', text: __('Change request cancelled.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Change Requests')" :subtitle="__('Track household and vehicle changes submitted to the HOA office.')" />
    <div class="space-y-3">
        @forelse ($this->requests as $request)
            <flux:card wire:key="owner-change-{{ $request->id }}" class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><flux:heading>{{ $request->type->label() }}</flux:heading><flux:text>{{ $request->property->code }} · {{ $request->created_at->format('M j, Y g:i A') }}</flux:text></div>
                    <x-status-pill :label="$request->status->value" />
                </div>
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($request->payload as $key => $value)
                        <div wire:key="change-value-{{ $request->id }}-{{ $key }}"><dt class="font-medium text-zinc-500">{{ str($key)->replace('_', ' ')->title() }}</dt><dd>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : ($value ?? '—') }}</dd></div>
                    @endforeach
                </dl>
                @if ($request->decision_note)<flux:callout>{{ $request->decision_note }}</flux:callout>@endif
                @if ($request->status === OwnerChangeStatus::Pending)<flux:button size="sm" variant="ghost" wire:click="cancel({{ $request->id }})">{{ __('Cancel request') }}</flux:button>@endif
            </flux:card>
        @empty
            <flux:card><flux:text>{{ __('No change requests yet.') }}</flux:text></flux:card>
        @endforelse
    </div>
</div>