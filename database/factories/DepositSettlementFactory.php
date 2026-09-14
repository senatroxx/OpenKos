<?php

namespace Database\Factories;

use App\Enums\DepositSettlementStatus;
use App\Models\DepositSettlement;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositSettlement>
 */
class DepositSettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'original_amount' => '1000000',
            'currency' => 'IDR',
            'status' => DepositSettlementStatus::Draft,
            'settlement_date' => now()->toDateString(),
            'refund_amount' => '1000000',
            'refund_reference' => null,
            'notes' => null,
        ];
    }
}
