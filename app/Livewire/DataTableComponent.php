<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithDataTable;
use Livewire\Component;

abstract class DataTableComponent extends Component
{
    use WithDataTable;
}
