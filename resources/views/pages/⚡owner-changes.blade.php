<?php

use App\Enums\OwnerChangeStatus;
use App\Enums\OwnerChangeType;
use App\Models\OwnerChangeRequest;
use App\Services\OwnerChangeRequestService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Owner Changes')] class extends Component
{
    public string $status = 'Pending';
    public string $decisionNote = '';
    public string $passNo = '';
    public string $passExpiresOn = '';

    #[Computed]
    public function requests()
    {
        return OwnerChangeRequest::query()
            ->with(['property', 'requester', 'reviewer', 'target'])
            ->when($this->status !== 'All', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(20);
    }

    public function approve(int $id, OwnerChangeRequestService $changes): void
    {
        $changeRequest = OwnerChangeRequest::query()->findOrFail($id);
        $this->authorize('review', $changeRequest);

        if ($changeRequest->type === OwnerChangeType::HouseStaffAdd) {
            $data = $this->validate([
                'passNo' => ['required', 'string', 'max:100', 'unique:house_staff,pass_no'],
                'passExpiresOn' => ['required', 'date', 'after:today'],
            ]);
            $changeRequest->update(['payload' => array_merge($changeRequest->payload, [
                'pass_no' => $data['passNo'],
                'pass_expires_on' => $data['passExpiresOn'],
            ])]);
        }

        $changes->approve($changeRequest, auth()->user(), $this->decisionNote ?: null);
        $this->reset(['decisionNote', 'passNo', 'passExpiresOn']);
        unset($this->requests);
        Flux::toast(variant: 'success', text: __('Change approved.'));
    }

    public function decline(int $id, OwnerChangeRequestService $changes): void
    {
        $changeRequest = OwnerChangeRequest::query()->findOrFail($id);
        $this->authorize('review', $changeRequest);
        $this->validate(['decisionNote' => ['required', 'string', 'max:2000']]);
        $changes->decline($changeRequest, auth()->user(), $this->decisionNote);
        $this->reset('decisionNote');
        unset($this->requests);
        Flux::toast(variant: 'success', text: __('Change declined.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Owner Changes')" :subtitle="__('Review requested household and vehicle updates before official records change.')" />
    <div class="flex flex-wrap gap-2">@foreach (['Pending', 'Approved', 'Declined', 'Cancelled', 'All'] as $option)<flux:button size="sm" :variant="$status === $option ? 'primary' : 'ghost'" wire:click="$set('status', '{{ $option }}')">{{ $option }}</flux:button>@endforeach</div>
    <div class="space-y-4">
        @forelse ($this->requests as $request)
            <flux:card wire:key="staff-owner-change-{{ $request->id }}" class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading>{{ $request->type->label() }}</flux:heading><flux:text>{{ $request->property->code }} · {{ $request->requester?->name ?? __('Deactivated user') }} · {{ $request->created_at->format('M j, Y g:i A') }}</flux:text></div><x-status-pill :label="$request->status->value" /></div>
                <dl class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">@foreach ($request->payload as $key => $value)<div wire:key="staff-change-value-{{ $request->id }}-{{ $key }}"><dt class="font-medium text-zinc-500">{{ str($key)->replace('_', ' ')->title() }}</dt><dd>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : ($value ?? '—') }}</dd></div>@endforeach</dl>
                @if ($request->status === OwnerChangeStatus::Pending)
                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($request->type === OwnerChangeType::HouseStaffAdd)<flux:input label="{{ __('Pass number') }}" wire:model="passNo" /><flux:input label="{{ __('Pass expiry') }}" type="date" wire:model="passExpiresOn" />@endif
                        <flux:textarea class="sm:col-span-2" label="{{ __('Decision note') }}" wire:model="decisionNote" />
                        <div class="flex gap-2 sm:col-span-2"><flux:button variant="primary" wire:click="approve({{ $request->id }})">{{ __('Approve') }}</flux:button><flux:button wire:click="decline({{ $request->id }})">{{ __('Decline') }}</flux:button></div>
                    </div>
                @elseif ($request->decision_note)
                    <flux:callout>{{ $request->decision_note }}</flux:callout>
                @endif
            </flux:card>
        @empty
            <flux:card><flux:text>{{ __('No owner change requests in this status.') }}</flux:text></flux:card>
        @endforelse
    </div>
    {{ $this->requests->links() }}
</div>