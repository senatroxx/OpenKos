<?php

namespace Database\Factories;

use App\Models\DepositDeduction;
use App\Models\DepositSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositDeduction>
 */
class DepositDeductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deposit_settlement_id' => DepositSettlement::factory(),
            'amount' => '100000',
            'reason' => 'Cleaning',
            'description' => null,
        ];
    }
}
