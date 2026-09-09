<?php

namespace Database\Factories;

use App\Enums\UtilityMeterType;
use App\Models\Unit;
use App\Models\UtilityMeter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityMeter>
 */
class UtilityMeterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'utility_type' => UtilityMeterType::Electricity,
            'identifier' => fake()->unique()->bothify('MTR-####'),
            'measurement_unit' => 'kWh',
            'rate' => '1000',
            'currency' => 'IDR',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
