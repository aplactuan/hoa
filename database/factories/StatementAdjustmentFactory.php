<?php

namespace Database\Factories;

use App\Models\DuesStatement;
use App\Models\StatementAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementAdjustment>
 */
class StatementAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dues_statement_id' => DuesStatement::factory(),
            'amount' => '25.00',
            'kind' => 'Credit',
            'reason' => 'Reconciled correction',
            'status' => 'Pending',
        ];
    }
}
