<?php

namespace Database\Factories;

use App\Enums\BillingUnit;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyRate>
 */
class PropertyRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'billing_interval' => 1,
            'billing_unit' => BillingUnit::Month,
            'amount' => fake()->numberBetween(1_000_000, 10_000_000),
            'currency' => app(MoneyConverter::class)->normalizeCurrency(),
            'is_active' => true,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }
}
