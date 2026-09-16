<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_template_id' => InspectionTemplate::factory(),
            'template_name' => fake()->words(3, true).' checklist',
            'property_id' => Property::factory(),
            'unit_id' => null,
            'lease_id' => null,
            'inspection_type' => InspectionType::Periodic,
            'inspection_date' => now()->toDateString(),
            'status' => InspectionStatus::Draft,
            'notes' => null,
            'damage_observations' => null,
            'inspector_id' => null,
            'completed_by' => null,
            'completed_at' => null,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'property_id' => $lease->unit->property_id,
            'unit_id' => $lease->unit_id,
            'lease_id' => $lease->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => InspectionStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
