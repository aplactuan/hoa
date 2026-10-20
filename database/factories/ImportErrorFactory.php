<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\ImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportError>
 */
class ImportErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => 2,
            'message' => 'Invalid row',
        ];
    }
}
