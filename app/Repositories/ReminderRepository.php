<?php

namespace App\Repositories;

use App\Data\Reminder\ReminderEvent;
use App\Models\ReminderLog;
use Illuminate\Database\UniqueConstraintViolationException;

class ReminderRepository
{
    public function recordIfAbsent(ReminderEvent $event, array $channels = ['whatsapp']): ?ReminderLog
    {
        try {
            return ReminderLog::create([
                'lease_id' => $event->lease->id,
                'period_start' => $event->periodStart,
                'period_end' => $event->periodEnd,
                'reminder_type' => $event->type->value,
                'overdue_days' => $event->overdueDays ?? ReminderLog::NON_OVERDUE_DAYS,
                'notification_class' => 'App\Notifications\RentReminder',
                'channel' => implode(',', $channels),
                'scheduled_for' => today(),
                'sent_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index !== 'reminder_logs_unique'
                && $exception->columns !== ['lease_id', 'period_start', 'reminder_type', 'overdue_days']) {
                throw $exception;
            }

            return null;
        }
    }
}
