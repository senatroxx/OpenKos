<?php

namespace Database\Factories;

use App\Enums\BillingUnit;
use App\Models\UnitType;
use App\Models\UnitTypeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitTypeRate>
 */
class UnitTypeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_type_id' => UnitType::factory(),
            'billing_interval' => 1,
            'billing_unit' => BillingUnit::Month,
            'amount' => '1500000',
            'currency' => 'IDR',
            'is_active' => true,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }
}
