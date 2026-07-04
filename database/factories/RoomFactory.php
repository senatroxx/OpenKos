<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

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
            'name' => fake()->numerify('Room #####'),
            'floor' => (string) fake()->numberBetween(1, 5),
            'size_sqm' => fake()->randomFloat(2, 12, 30),
            'capacity' => 1,
            'status' => RoomStatus::Available,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        // Seed a default monthly rate so a bare Room::factory()->create() is leasable.
        return $this->afterCreating(function (Room $room) {
            if (! $room->rates()->exists()) {
                $room->rates()->create([
                    'billing_interval' => 1,
                    'billing_unit' => 'month',
                    'amount' => fake()->numberBetween(500_000, 3_000_000),
                    'is_active' => DB::raw('true'),
                ]);
            }
        });
    }

    /**
     * Give the room a specific monthly rate.
     */
    public function withRate(int|string $amount): static
    {
        return $this->afterCreating(function (Room $room) use ($amount) {
            $room->rates()->updateOrCreate(
                ['billing_interval' => 1, 'billing_unit' => 'month'],
                ['amount' => $amount, 'is_active' => DB::raw('true')],
            );
        });
    }

    public function occupied(): static
    {
        return $this->state(['status' => RoomStatus::Occupied]);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => RoomStatus::Maintenance]);
    }

    public function unavailable(): static
    {
        return $this->state(['status' => RoomStatus::Unavailable]);
    }
}
