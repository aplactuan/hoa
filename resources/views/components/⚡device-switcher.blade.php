<?php

use App\Models\Device;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $deviceId = null;

    public function mount(): void
    {
        $current = session('device_id');
        $this->deviceId = is_numeric($current) ? (int) $current : null;
    }

    public function updatedDeviceId(mixed $deviceId): void
    {
        if ($deviceId === null || $deviceId === '') {
            $this->deviceId = null;
            session()->forget('device_id');

            return;
        }

        $device = Device::query()->whereKey((int) $deviceId)->where('is_active', true)->first();

        if ($device === null) {
            $this->deviceId = null;
            session()->forget('device_id');

            return;
        }

        $this->deviceId = $device->id;
        session(['device_id' => $device->id]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Device>
     */
    #[Computed]
    public function devices()
    {
        return Device::query()->where('is_active', true)->orderBy('name')->get();
    }
};
?>

<div>
    <flux:select wire:model.live="deviceId" placeholder="{{ __('This device') }}" size="sm">
        <flux:select.option value="">{{ __('Unassigned device') }}</flux:select.option>
        @foreach ($this->devices as $device)
            <flux:select.option :value="$device->id" wire:key="device-{{ $device->id }}">
                {{ $device->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
