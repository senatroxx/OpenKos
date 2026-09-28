<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\ReservationStatus;
use App\Models\Application;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->state(['status' => ApplicationStatus::Accepted]),
            'status' => ReservationStatus::Pending,
            'move_in_date' => now()->addWeek()->toDateString(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);
    }
}
