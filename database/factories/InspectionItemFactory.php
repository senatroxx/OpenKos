<?php

namespace Database\Factories;

use App\Enums\InspectionItemCondition;
use App\Models\Inspection;
use App\Models\InspectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionItem>
 */
class InspectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'label' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'position' => 0,
            'condition' => null,
            'notes' => null,
        ];
    }

    public function assessed(): static
    {
        return $this->state([
            'condition' => fake()->randomElement(InspectionItemCondition::cases()),
        ]);
    }
}
