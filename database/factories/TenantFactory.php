<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'id_card_number' => fake()->numerify('################'),
            'is_active' => true,
        ];
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
