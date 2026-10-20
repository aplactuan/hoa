<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\WithPagination;

trait WithDataTable
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function filterStatus(string $status): void
    {
        if (! array_key_exists($status, $this->statusChips())) {
            return;
        }

        $this->status = $status;
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyListing(Builder $query, ?string $searchColumn = 'name'): Builder
    {
        if ($this->search !== '' && $searchColumn !== null) {
            $query->where($searchColumn, 'like', '%'.$this->search.'%');
        }

        if ($this->status === 'active') {
            $query->where($query->getModel()->getTable().'.is_active', true);
        } elseif ($this->status === 'inactive') {
            $query->where($query->getModel()->getTable().'.is_active', false);
        }

        $columns = $this->sortableColumns();
        $column = in_array($this->sortBy, $columns, true) ? $this->sortBy : $columns[0];
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction);
    }

    /**
     * @return array<string, string>
     */
    protected function statusChips(): array
    {
        return [
            'all' => 'All',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    /**
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;
}
