<?php

namespace Database\Factories;

use App\Enums\AmenityScope;
use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'icon' => null,
            'scope' => AmenityScope::Both,
            'is_active' => true,
        ];
    }
}
