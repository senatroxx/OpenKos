<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitType>
 */
class UnitTypeFactory extends Factory
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
            'name' => fake()->randomElement(['Studio', 'One Bedroom', 'Two Bedroom']),
            'description' => fake()->optional()->sentence(),
            'bedrooms' => fake()->optional()->numberBetween(0, 4),
            'bathrooms' => fake()->optional()->randomFloat(1, 1, 3),
            'size_sqm' => fake()->optional()->randomFloat(2, 15, 80),
            'furnishing' => fake()->optional()->randomElement(['furnished', 'semi-furnished', 'unfurnished']),
            'is_active' => true,
            'is_published' => false,
        ];
    }
}
