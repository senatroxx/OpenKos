<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'room_id' => Room::factory(),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'end_date' => null,
            'monthly_rent' => null,
            'deposit_amount' => fake()->numberBetween(500_000, 1_000_000),
            'deposit_paid_at' => now(),
            'rent_due_day' => 1,
            'status' => 'active',
            'termination_date' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'status' => 'terminated',
            'termination_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'termination_reason' => fake()->sentence(),
        ]);
    }
}
