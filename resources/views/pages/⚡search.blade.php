<?php

use App\Enums\Permission;
use App\Models\Device;
use App\Models\DuesStatement;
use App\Models\Facility;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Resident;
use App\Models\ServiceCase;
use App\Models\Section;
use App\Models\Street;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Search')] class extends Component
{
    public string $q = '';

    public function mount(): void
    {
        $this->q = mb_substr(trim(request()->string('q')->toString()), 0, 100);
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, url: string|null}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = trim(str_replace(['%', '_'], '', $this->q));

        if (mb_strlen($term) < 2) {
            return [];
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $like = '%'.$term.'%';
        $groups = [];

        if ($user->hasPermission(Permission::PropertiesView)) {
            $groups[] = [
                'label' => __('Properties'),
                'items' => Property::query()
                    ->where('code', 'like', $like)
                    ->orWhere('block', 'like', $like)
                    ->orWhere('lot', 'like', $like)
                    ->limit(8)->get()
                    ->map(fn (Property $property): array => [
                        'label' => $property->code.' · Block '.$property->block.' Lot '.$property->lot,
                        'url' => route('properties.show', $property),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::OwnersView)) {
            $groups[] = [
                'label' => __('Owners'),
                'items' => Owner::query()->where('name', 'like', $like)->orWhere('code', 'like', $like)
                    ->limit(8)->get()->map(fn (Owner $owner): array => [
                        'label' => $owner->name.' · '.$owner->code,
                        'url' => route('properties.show', $owner->property_id),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::ResidentsView)) {
            $groups[] = [
                'label' => __('Residents'),
                'items' => Resident::query()->where('name', 'like', $like)->limit(8)->get()
                    ->map(fn (Resident $resident): array => [
                        'label' => $resident->name.' · '.$resident->status,
                        'url' => route('properties.show', $resident->property_id),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::VehiclesView)) {
            $groups[] = [
                'label' => __('Vehicles'),
                'items' => Vehicle::query()->where('plate_no', 'like', $like)->orWhere('driver_name', 'like', $like)
                    ->limit(8)->get()->map(fn (Vehicle $vehicle): array => [
                        'label' => $vehicle->plate_no.' · '.$vehicle->driver_name,
                        'url' => route('properties.show', $vehicle->property_id),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::StatementsView)) {
            $groups[] = [
                'label' => __('Statements'),
                'items' => DuesStatement::query()->where('code', 'like', $like)->limit(8)->get()
                    ->map(fn (DuesStatement $statement): array => [
                        'label' => $statement->code,
                        'url' => route('statements.print', $statement),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::ReceiptsView)) {
            $groups[] = [
                'label' => __('Payments'),
                'items' => Payment::query()->where('or_no', 'like', $like)->limit(8)->get()
                    ->map(fn (Payment $payment): array => [
                        'label' => $payment->or_no,
                        'url' => route('receipts.print', $payment),
                    ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::RequestsView)) {
            $cases = ServiceCase::query()->where(function ($query) use ($like): void {
                $query->where('code', 'like', $like)->orWhere('description', 'like', $like);
            });

            if ($user->hasRole(App\Enums\RoleName::SecurityGuard)) {
                $cases->where('created_by', $user->id);
            }

            $groups[] = [
                'label' => __('Requests'),
                'items' => $cases->limit(8)->get()->map(fn (ServiceCase $case): array => [
                    'label' => $case->code.' · '.$case->status,
                    'url' => route('requests.index'),
                ])->all(),
            ];
        }

        if ($user->hasPermission(Permission::UsersView)) {
            $groups[] = [
                'label' => __('Users'),
                'items' => User::query()
                    ->where('name', 'like', $like)
                    ->orderBy('name')
                    ->limit(8)
                    ->get()
                    ->map(fn (User $result): array => ['label' => $result->name, 'url' => route('users.index')])
                    ->all(),
            ];
        }

        if ($user->hasPermission(Permission::MasterDataManage)) {
            $groups[] = [
                'label' => __('Streets'),
                'items' => Street::query()->where('name', 'like', $like)->orderBy('name')->limit(8)->get()
                    ->map(fn (Street $street): array => ['label' => $street->name, 'url' => route('master-data.index')])->all(),
            ];
            $groups[] = [
                'label' => __('Sections'),
                'items' => Section::query()->where('name', 'like', $like)->orderBy('name')->limit(8)->get()
                    ->map(fn (Section $section): array => ['label' => $section->name, 'url' => route('master-data.index')])->all(),
            ];
            $groups[] = [
                'label' => __('Facilities'),
                'items' => Facility::query()->where('name', 'like', $like)->orderBy('name')->limit(8)->get()
                    ->map(fn (Facility $facility): array => ['label' => $facility->name, 'url' => route('master-data.index')])->all(),
            ];
        }

        if ($user->hasPermission(Permission::DevicesManage)) {
            $groups[] = [
                'label' => __('Devices'),
                'items' => Device::query()->where('name', 'like', $like)->orderBy('name')->limit(8)->get()
                    ->map(fn (Device $device): array => ['label' => $device->name, 'url' => route('devices.index')])->all(),
            ];
        }

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Search')" :subtitle="$q === '' ? __('Type a name in the search box.') : __('Results for “:query”.', ['query' => $q])" />

    @forelse ($this->groups as $group)
        <section wire:key="search-{{ $group['label'] }}" class="space-y-2">
            <flux:heading size="lg">{{ $group['label'] }}</flux:heading>
            <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($group['items'] as $item)
                    <li wire:key="search-item-{{ $group['label'] }}-{{ $item['label'] }}" class="px-4 py-3 text-sm">
                        @if ($item['url'])<a href="{{ $item['url'] }}" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $item['label'] }}</a>@else {{ $item['label'] }} @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        @if ($q !== '')
            <flux:text>{{ __('No matching records.') }}</flux:text>
        @endif
    @endforelse
</div>
