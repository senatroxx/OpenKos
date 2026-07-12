<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\ReminderLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderLog>
 */
class ReminderLogFactory extends Factory
{
    protected $model = ReminderLog::class;

    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'reminder_type' => fake()->randomElement(['upcoming', 'due_today', 'overdue']),
            'overdue_days' => null,
            'notification_class' => 'App\Notifications\RentReminder',
            'channel' => 'whatsapp',
            'scheduled_for' => now()->toDateString(),
            'sent_at' => now(),
        ];
    }
}
