<?php

namespace App\Repositories;

use App\Data\Reminder\ReminderEvent;
use App\Models\ReminderLog;
use Illuminate\Database\QueryException;

class ReminderRepository
{
    public function alreadySent(ReminderEvent $event): bool
    {
        return ReminderLog::where('lease_id', $event->lease->id)
            ->where('period_start', $event->periodStart)
            ->where('reminder_type', $event->type->value)
            ->where('overdue_days', $event->overdueDays)
            ->exists();
    }

    public function recordIfAbsent(ReminderEvent $event, array $channels = ['whatsapp']): ?ReminderLog
    {
        if ($this->alreadySent($event)) {
            return null;
        }

        try {
            return ReminderLog::create([
                'lease_id' => $event->lease->id,
                'period_start' => $event->periodStart,
                'period_end' => $event->periodEnd,
                'reminder_type' => $event->type->value,
                'overdue_days' => $event->overdueDays,
                'notification_class' => 'App\Notifications\RentReminder',
                'channel' => implode(',', $channels),
                'scheduled_for' => today(),
                'sent_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            return null;
        }
    }
}
