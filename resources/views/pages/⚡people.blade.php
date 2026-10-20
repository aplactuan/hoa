<?php

use App\Enums\Permission;
use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Vehicle;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('People and vehicles')] class extends Component
{
    use WithPagination;

    public string $kind = '';
    public string $search = '';

    public function mount(): void
    {
        $this->kind = match (request()->route()?->getName()) {
            'owners.index' => 'Owners',
            'residents.index' => 'Residents',
            'family.index' => 'Family Members',
            'house-staff.index' => 'House Staff',
            'vehicles.index' => 'Vehicles & Stickers',
            default => abort(404),
        };
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        $model = match ($this->kind) {
            'Owners' => Owner::class,
            'Residents' => Resident::class,
            'Family Members' => FamilyMember::class,
            'House Staff' => HouseStaff::class,
            default => Vehicle::class,
        };
        $query = $model::query()->with('property');

        if ($this->kind === 'Vehicles & Stickers') {
            $query->with('stickers')->when($this->search !== '', fn ($query) => $query->where('plate_no', 'like', '%'.$this->search.'%')->orWhere('driver_name', 'like', '%'.$this->search.'%'));
        } else {
            $query->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'));
        }

        return $query->latest()->paginate(20);
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header :title="$kind" subtitle="Find a record and open its property profile." />
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or plate" class="max-w-sm" />
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-3">ID</th><th class="p-3">Name or plate</th><th class="p-3">Property</th><th class="p-3">Details</th><th class="p-3">Status</th></tr></thead><tbody>
            @forelse ($this->records as $record)
                <tr wire:key="person-{{ $kind }}-{{ $record->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                    <td class="p-3">{{ $record->code }}</td>
                    <td class="p-3">{{ $kind === 'Vehicles & Stickers' ? $record->plate_no : $record->name }}</td>
                    <td class="p-3">
                        @if (auth()->user()->hasPermission(Permission::PropertiesView))
                            <a class="text-emerald-700 hover:underline dark:text-emerald-400" href="{{ route('properties.show', $record->property) }}">{{ $record->property->code }}</a>
                        @else
                            Block {{ $record->property->block }} · Lot {{ $record->property->lot }}
                        @endif
                    </td>
                    <td class="p-3">{{ match ($kind) { 'Owners' => $record->ownership_type, 'Residents' => $record->occupancy_type, 'Family Members' => $record->relationship, 'House Staff' => $record->role.' · '.$record->pass_no, default => $record->type.' · '.$record->driver_name } }}</td>
                    <td class="p-3"><x-status-pill :label="$record->status" /></td>
                </tr>
            @empty <tr><td colspan="5" class="p-6 text-center">No records found.</td></tr> @endforelse
        </tbody></table></div>
        {{ $this->records->links() }}
    </div>
</div>
