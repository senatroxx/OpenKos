<?php

namespace Database\Factories;

use App\Models\MaintenanceTicket;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTicket>
 */
class MaintenanceTicketFactory extends Factory
{
    protected $model = MaintenanceTicket::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'reported',
            'priority' => 'medium',
            'assigned_to' => null,
            'cost' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }
}
