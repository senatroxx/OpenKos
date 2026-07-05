<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->numerify('Unit #####'),
            'floor' => (string) fake()->numberBetween(1, 5),
            'size_sqm' => fake()->randomFloat(2, 12, 30),
            'capacity' => 1,
            'status' => UnitStatus::Available,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Unit $unit) {
            if (! $unit->rates()->exists()) {
                $unit->rates()->create([
                    'billing_interval' => 1,
                    'billing_unit' => 'month',
                    'amount' => fake()->numberBetween(500_000, 3_000_000),
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Give the unit a specific monthly rate.
     */
    public function withRate(int|string $amount): static
    {
        return $this->afterCreating(function (Unit $unit) use ($amount) {
            $unit->rates()->updateOrCreate(
                ['billing_interval' => 1, 'billing_unit' => 'month'],
                ['amount' => $amount, 'is_active' => true],
            );
        });
    }

    public function occupied(): static
    {
        return $this->state(['status' => UnitStatus::Occupied]);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => UnitStatus::Maintenance]);
    }

    public function unavailable(): static
    {
        return $this->state(['status' => UnitStatus::Unavailable]);
    }
}
