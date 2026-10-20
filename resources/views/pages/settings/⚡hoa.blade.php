<?php

use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Association settings')] class extends Component
{
    public string $dues_rate_per_sqm = '';

    public string $penalty_percent = '';

    public int $due_day = 1;

    public int $grace_days = 0;

    public int $expiry_warning_days = 45;

    public string $hoa_name = '';

    public string $hoa_address = '';

    public function mount(): void
    {
        $setting = Setting::current();
        $this->authorize('view', $setting);

        $this->dues_rate_per_sqm = (string) $setting->dues_rate_per_sqm;
        $this->penalty_percent = (string) $setting->penalty_percent;
        $this->due_day = $setting->due_day;
        $this->grace_days = $setting->grace_days;
        $this->expiry_warning_days = $setting->expiry_warning_days;
        $this->hoa_name = $setting->hoa_name;
        $this->hoa_address = $setting->hoa_address;
    }

    public function save(): void
    {
        $setting = Setting::current();
        $this->authorize('update', $setting);

        $validated = $this->validate([
            'dues_rate_per_sqm' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'penalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            'hoa_name' => ['required', 'string', 'max:255'],
            'hoa_address' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($setting, $validated): void {
            $setting->update($validated);
        });

        Flux::toast(variant: 'success', text: __('Association settings saved.'));
    }

    public function showHistory(): void
    {
        $setting = Setting::current();

        $this->dispatch(
            'open-record',
            title: $setting->hoa_name,
            type: Setting::class,
            id: $setting->id,
        );
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <x-page-header :title="__('Settings')" :subtitle="__('Rates and the association name used on printouts.')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="clock" wire:click="showHistory">{{ __('Change history') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Dues rate per sqm') }}</flux:label>
                <flux:input wire:model="dues_rate_per_sqm" inputmode="decimal" />
                <flux:error name="dues_rate_per_sqm" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Penalty percent') }}</flux:label>
                <flux:input wire:model="penalty_percent" inputmode="decimal" />
                <flux:error name="penalty_percent" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Due day') }}</flux:label>
                <flux:input wire:model="due_day" type="number" min="1" max="28" />
                <flux:error name="due_day" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Grace days') }}</flux:label>
                <flux:input wire:model="grace_days" type="number" min="0" />
                <flux:error name="grace_days" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('Expiry warning window (days)') }}</flux:label>
                <flux:input wire:model="expiry_warning_days" type="number" min="1" />
                <flux:error name="expiry_warning_days" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('HOA name') }}</flux:label>
                <flux:input wire:model="hoa_name" />
                <flux:error name="hoa_name" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('HOA address') }}</flux:label>
                <flux:textarea wire:model="hoa_address" rows="3" />
                <flux:error name="hoa_address" />
            </flux:field>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
