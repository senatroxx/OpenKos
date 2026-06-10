<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        return [
            'primary_tenant_id' => Tenant::factory(),
            'room_id' => Room::factory(),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'end_date' => null,
            'rent_amount' => null,
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'is_custom_price' => DB::raw('false'),
            'deposit_amount' => fake()->numberBetween(500_000, 1_000_000),
            'deposit_paid_at' => now(),
            'rent_due_day' => 1,
            'status' => 'active',
            'termination_date' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Lease $lease) {
            $lease->tenants()->attach($lease->primary_tenant_id, ['is_primary' => DB::raw('true')]);
        });
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
