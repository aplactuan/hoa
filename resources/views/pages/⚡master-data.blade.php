<?php

use App\Enums\Permission;
use App\Livewire\DataTableComponent;
use App\Models\Section;
use App\Models\User;
use App\Support\MasterDataCatalog;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Master Data')] class extends DataTableComponent
{
    public string $catalog = 'sections';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $sectionId = null;

    public string $defaultFee = '';

    public string $defaultFine = '';

    public string $fee = '';

    public string $deposit = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Section::class);
    }

    public function showCatalog(string $catalog): void
    {
        MasterDataCatalog::find($catalog);
        $this->catalog = $catalog;
        $this->resetPage();
        $this->search = '';
        $this->status = 'all';
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
        $this->showModal = false;
        $this->resetForm();
    }

    public function createRecord(): void
    {
        $this->authorize('create', $this->modelClass());
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = $this->modelClass()::query()->findOrFail($id);
        $this->authorize('update', $record);
        $fields = $this->fields();

        $this->editingId = $record->getKey();
        $this->name = (string) $record->getAttribute('name');
        $this->isActive = (bool) $record->getAttribute('is_active');
        $this->sectionId = in_array('section_id', $fields, true) ? $record->getAttribute('section_id') : null;
        $this->defaultFee = in_array('default_fee', $fields, true) ? (string) $record->getAttribute('default_fee') : '';
        $this->defaultFine = in_array('default_fine', $fields, true) ? (string) $record->getAttribute('default_fine') : '';
        $this->fee = in_array('fee', $fields, true) ? (string) $record->getAttribute('fee') : '';
        $this->deposit = in_array('deposit', $fields, true) ? (string) $record->getAttribute('deposit') : '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $record = $this->editingId
            ? $this->modelClass()::query()->findOrFail($this->editingId)
            : new ($this->modelClass());

        $this->authorize($this->editingId ? 'update' : 'create', $this->editingId ? $record : $this->modelClass());

        $record->fill($this->attributes($validated))->save();
        $this->showModal = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: __('Lookup saved.'));
    }

    public function delete(int $id): void
    {
        $record = $this->modelClass()::query()->findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();
        Flux::toast(variant: 'success', text: __('Lookup deleted.'));
    }

    public function showHistory(int $id): void
    {
        $record = $this->modelClass()::query()->findOrFail($id);
        $this->authorize('view', $record);

        $this->dispatch(
            'open-record',
            title: (string) $record->getAttribute('name'),
            type: $record::class,
            id: (int) $record->getKey(),
        );
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Model>
     */
    #[Computed]
    public function records()
    {
        $query = $this->modelClass()::query();

        if ($this->modelClass() === \App\Models\Street::class) {
            $query->with('section');
        }

        return $this->applyListing($query)->paginate(10);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Section>
     */
    #[Computed]
    public function sections()
    {
        return Section::query()->orderBy('name')->get();
    }

    public function moneyTotal(string $column): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasPermission(Permission::FinancialView)) {
            return null;
        }

        if (! in_array($column, $this->fields(), true)) {
            return null;
        }

        return Money::format($this->modelClass()::query()->sum($column));
    }

    public function showsMoney(string $column): bool
    {
        return in_array($column, $this->fields(), true);
    }

    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        return MasterDataCatalog::find($this->catalog)['model'];
    }

    /**
     * @return list<string>
     */
    protected function fields(): array
    {
        return MasterDataCatalog::find($this->catalog)['fields'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];

        if (in_array('section_id', $this->fields(), true)) {
            $rules['sectionId'] = ['nullable', 'integer', Rule::exists('sections', 'id')];
        }

        foreach (['defaultFee' => 'default_fee', 'defaultFine' => 'default_fine', 'fee' => 'fee', 'deposit' => 'deposit'] as $property => $column) {
            if (in_array($column, $this->fields(), true)) {
                $rules[$property] = ['required', 'numeric', 'min:0', 'max:99999999.99'];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function attributes(array $validated): array
    {
        $attributes = [
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['isActive'] ?? false),
        ];

        if (in_array('section_id', $this->fields(), true)) {
            $attributes['section_id'] = $validated['sectionId'] ?? null;
        }

        $map = [
            'default_fee' => 'defaultFee',
            'default_fine' => 'defaultFine',
            'fee' => 'fee',
            'deposit' => 'deposit',
        ];

        foreach ($map as $column => $property) {
            if (in_array($column, $this->fields(), true)) {
                $attributes[$column] = $validated[$property];
            }
        }

        return $attributes;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->sectionId = null;
        $this->defaultFee = '';
        $this->defaultFine = '';
        $this->fee = '';
        $this->deposit = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name'];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Master Data')" :subtitle="__('Streets, fees, fines, and the other lists the later modules share.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createRecord">{{ __('Add') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2">
        @foreach (\App\Support\MasterDataCatalog::all() as $key => $definition)
            <flux:button
                size="sm"
                :variant="$catalog === $key ? 'primary' : 'ghost'"
                wire:click="showCatalog('{{ $key }}')"
                wire:key="catalog-{{ $key }}"
            >
                {{ $definition['label'] }}
            </flux:button>
        @endforeach
    </div>

    <x-data-table :records="$this->records" :status="$status">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                @if ($this->showsMoney('section_id') || $catalog === 'streets')
                    <flux:table.column>{{ __('Section') }}</flux:table.column>
                @endif
                @if ($this->showsMoney('default_fee'))
                    <flux:table.column align="end">{{ __('Default fee') }}</flux:table.column>
                @endif
                @if ($this->showsMoney('default_fine'))
                    <flux:table.column align="end">{{ __('Default fine') }}</flux:table.column>
                @endif
                @if ($this->showsMoney('fee'))
                    <flux:table.column align="end">{{ __('Fee') }}</flux:table.column>
                @endif
                @if ($this->showsMoney('deposit'))
                    <flux:table.column align="end">{{ __('Deposit') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->records as $record)
                    <flux:table.row wire:key="lookup-{{ $catalog }}-{{ $record->getKey() }}">
                        <flux:table.cell>{{ $record->getAttribute('name') }}</flux:table.cell>
                        @if ($catalog === 'streets')
                            <flux:table.cell>{{ $record->section?->name ?? '—' }}</flux:table.cell>
                        @endif
                        @if ($this->showsMoney('default_fee'))
                            <flux:table.cell align="end">{{ \App\Support\Money::format($record->getAttribute('default_fee')) ?? '—' }}</flux:table.cell>
                        @endif
                        @if ($this->showsMoney('default_fine'))
                            <flux:table.cell align="end">{{ \App\Support\Money::format($record->getAttribute('default_fine')) ?? '—' }}</flux:table.cell>
                        @endif
                        @if ($this->showsMoney('fee'))
                            <flux:table.cell align="end">{{ \App\Support\Money::format($record->getAttribute('fee')) ?? '—' }}</flux:table.cell>
                        @endif
                        @if ($this->showsMoney('deposit'))
                            <flux:table.cell align="end">{{ \App\Support\Money::format($record->getAttribute('deposit')) ?? '—' }}</flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <x-status-pill :active="(bool) $record->getAttribute('is_active')" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $record->getKey() }})">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="delete({{ $record->getKey() }})">{{ __('Delete') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="showHistory({{ $record->getKey() }})">
                                    {{ __('History') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($this->moneyTotal('default_fee') || $this->moneyTotal('default_fine') || $this->moneyTotal('fee') || $this->moneyTotal('deposit'))
            <x-slot:totals>
                <div class="flex flex-wrap gap-4">
                    @if ($this->moneyTotal('default_fee'))
                        <span>{{ __('Default fees') }}: {{ $this->moneyTotal('default_fee') }}</span>
                    @endif
                    @if ($this->moneyTotal('default_fine'))
                        <span>{{ __('Default fines') }}: {{ $this->moneyTotal('default_fine') }}</span>
                    @endif
                    @if ($this->moneyTotal('fee'))
                        <span>{{ __('Fees') }}: {{ $this->moneyTotal('fee') }}</span>
                    @endif
                    @if ($this->moneyTotal('deposit'))
                        <span>{{ __('Deposits') }}: {{ $this->moneyTotal('deposit') }}</span>
                    @endif
                </div>
            </x-slot:totals>
        @endif
    </x-data-table>

    <x-form-modal :title="$editingId ? __('Edit lookup') : __('Add lookup')">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" />
            <flux:error name="name" />
        </flux:field>

        @if ($catalog === 'streets')
            <flux:field>
                <flux:label>{{ __('Section') }}</flux:label>
                <flux:select wire:model="sectionId" placeholder="{{ __('No section') }}">
                    <flux:select.option value="">{{ __('No section') }}</flux:select.option>
                    @foreach ($this->sections as $section)
                        <flux:select.option :value="$section->id" wire:key="section-option-{{ $section->id }}">{{ $section->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="sectionId" />
            </flux:field>
        @endif

        @if ($this->showsMoney('default_fee'))
            <flux:field>
                <flux:label>{{ __('Default fee') }}</flux:label>
                <flux:input wire:model="defaultFee" inputmode="decimal" />
                <flux:error name="defaultFee" />
            </flux:field>
        @endif

        @if ($this->showsMoney('default_fine'))
            <flux:field>
                <flux:label>{{ __('Default fine') }}</flux:label>
                <flux:input wire:model="defaultFine" inputmode="decimal" />
                <flux:error name="defaultFine" />
            </flux:field>
        @endif

        @if ($this->showsMoney('fee'))
            <flux:field>
                <flux:label>{{ __('Fee') }}</flux:label>
                <flux:input wire:model="fee" inputmode="decimal" />
                <flux:error name="fee" />
            </flux:field>
        @endif

        @if ($this->showsMoney('deposit'))
            <flux:field>
                <flux:label>{{ __('Deposit') }}</flux:label>
                <flux:input wire:model="deposit" inputmode="decimal" />
                <flux:error name="deposit" />
            </flux:field>
        @endif

        <flux:switch wire:model="isActive" :label="__('Active')" />
    </x-form-modal>
</div>
