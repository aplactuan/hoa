<?php

use App\Enums\RoleName;
use App\Models\ImportBatch;
use App\Services\SpreadsheetImport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Spreadsheet import')] class extends Component
{
    use WithFileUploads;

    public string $source = 'Properties';
    public $file;
    public ?int $batchId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(RoleName::Administrator), 403);
    }

    #[Computed]
    public function batch(): ?ImportBatch
    {
        return $this->batchId ? ImportBatch::query()->with('errors')->find($this->batchId) : null;
    }

    #[Computed]
    public function recentBatches()
    {
        return ImportBatch::query()->latest()->limit(10)->get();
    }

    public function preview(SpreadsheetImport $import): void
    {
        abort_unless(auth()->user()->hasRole(RoleName::Administrator), 403);
        $this->validate(['source' => ['required', 'string'], 'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $batch = $import->preview($this->source, $this->file->getRealPath(), $this->file->getClientOriginalName(), auth()->id());
        $this->batchId = $batch->id;
        unset($this->batch, $this->recentBatches);
    }

    public function import(SpreadsheetImport $import): void
    {
        abort_unless(auth()->user()->hasRole(RoleName::Administrator), 403);
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], 'batchId' => ['required', 'integer', 'exists:import_batches,id']]);
        $import->commit(ImportBatch::query()->findOrFail($this->batchId), $this->file->getRealPath(), auth()->id());
        $this->reset('file');
        unset($this->batch, $this->recentBatches);
    }
};
?>

<div class="space-y-6">
    <x-page-header title="Spreadsheet import" subtitle="Preview CSV rows and errors before adding records." />
    <flux:card class="space-y-4">
        <flux:heading size="lg">CSV layout</flux:heading>
        <flux:text>Import properties first, then owners, residents, vehicles, and opening balances. Dates use YYYY-MM-DD. Each file must have the exact header shown below.</flux:text>
        <div class="space-y-2 text-sm">
            @foreach (app(SpreadsheetImport::class)->templates() as $name => $columns)
                <p><strong>{{ $name }}:</strong> <code>{{ implode(',', $columns) }}</code></p>
            @endforeach
        </div>
    </flux:card>
    <flux:card>
        <form wire:submit="preview" class="flex flex-wrap items-end gap-3">
            <label class="text-sm">Data type<select wire:model="source" class="block rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (app(SpreadsheetImport::class)->templates() as $name => $columns)<option>{{ $name }}</option>@endforeach</select></label>
            <input type="file" wire:model="file" accept=".csv,text/csv" class="text-sm" />
            <flux:button type="submit" variant="primary">Preview import</flux:button>
        </form>
        @error('file') <flux:text class="mt-2 text-red-600">{{ $message }}</flux:text> @enderror
    </flux:card>
    @if ($this->batch)
        <flux:card class="space-y-3">
            <flux:heading size="lg">Preview: {{ $this->batch->filename }}</flux:heading>
            <flux:text>{{ $this->batch->row_count }} valid rows · {{ $this->batch->error_count }} errors · {{ $this->batch->status }}</flux:text>
            @foreach ($this->batch->errors as $error)
                <flux:text class="text-red-600">Row {{ $error->row_number }}: {{ $error->message }}</flux:text>
            @endforeach
            @if ($this->batch->error_count === 0 && $this->batch->status === 'Previewed')
                <flux:button variant="primary" wire:click="import">Import {{ $this->batch->row_count }} rows</flux:button>
            @endif
            @if ($this->batch->status === 'Imported' && $this->batch->source === 'Opening Balances')
                <flux:text>Reconciled opening balance total: ₱{{ number_format((float) $this->batch->reconciled_amount, 2) }}</flux:text>
            @endif
        </flux:card>
    @endif
    <flux:card class="space-y-2">
        <flux:heading size="lg">Recent batches</flux:heading>
        @foreach ($this->recentBatches as $batch)
            <flux:text>{{ $batch->filename }} · {{ $batch->source }} · {{ $batch->status }} · {{ $batch->imported_count }}/{{ $batch->row_count }} imported</flux:text>
        @endforeach
    </flux:card>
</div>
