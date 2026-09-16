<?php

namespace Database\Factories;

use App\Enums\InspectionType;
use App\Models\InspectionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionTemplate>
 */
class InspectionTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' checklist',
            'inspection_type' => InspectionType::Periodic,
            'is_active' => true,
        ];
    }

    public function moveIn(): static
    {
        return $this->state(['inspection_type' => InspectionType::MoveIn]);
    }

    public function moveOut(): static
    {
        return $this->state(['inspection_type' => InspectionType::MoveOut]);
    }
}
