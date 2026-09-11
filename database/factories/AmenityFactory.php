<?php

namespace Database\Factories;

use App\Enums\AmenityScope;
use App\Models\Amenity;
use App\Models\Property;
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
            'owner_property_id' => null,
            'name' => fake()->unique()->words(2, true),
            'scope' => AmenityScope::Both,
            'is_active' => true,
        ];
    }

    public function customFor(Property $property): static
    {
        return $this->state([
            'owner_property_id' => $property->id,
        ]);
    }
}
