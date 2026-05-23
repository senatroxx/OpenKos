<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->numerify('Room ###'),
            'floor' => (string) fake()->numberBetween(1, 5),
            'base_price' => fake()->numberBetween(500_000, 3_000_000),
            'size_sqm' => fake()->randomFloat(2, 12, 30),
            'capacity' => 1,
            'status' => 'available',
            'notes' => null,
        ];
    }
}
