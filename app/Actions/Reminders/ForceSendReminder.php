<?php

namespace App\Actions\Reminders;

use App\Data\Reminder\ReminderEvent;
use App\Enums\ReminderType;
use App\Models\Lease;
use App\Models\Setting;
use App\Notifications\RentReminder;
use App\Repositories\ReminderRepository;
use Carbon\Carbon;

class ForceSendReminder
{
    public function __construct(
        private ReminderRepository $repository,
        private Setting $settings,
    ) {}

    public function execute(Lease $lease): string
    {
        $lease->load(['primaryTenant', 'payments']);
        $tenant = $lease->primaryTenant;

        $channels = $this->settings->get()->reminder_channels ?? ['log'];
        $hasContact = $tenant?->phone || ($tenant?->email && in_array('mail', $channels));

        if (! $hasContact) {
            return 'no_contact';
        }

        $period = $lease->scheduleForReminder()->first(fn ($p) => $p->status !== 'paid');

        if (! $period) {
            return 'all_paid';
        }

        $today = now()->startOfDay();
        $dueDate = Carbon::parse($period->due_date)->startOfDay();
        $overdueDays = $dueDate->lessThan($today) ? (int) $dueDate->diffInDays($today) : null;

        $type = match ($period->status) {
            'upcoming' => ReminderType::Upcoming,
            'due' => ReminderType::DueToday,
            default => ReminderType::Overdue,
        };

        $event = new ReminderEvent(
            lease: $lease,
            type: $type,
            periodStart: $period->period_start->toDateString(),
            periodEnd: $period->period_end->toDateString(),
            dueDate: $period->due_date->toDateString(),
            amount: (int) ($lease->rent_amount * 100),
            overdueDays: $overdueDays,
        );

        $log = $this->repository->recordIfAbsent($event, $channels);

        if (! $log) {
            return 'already_sent';
        }

        $tenant->notifyNow(new RentReminder($event));

        return 'sent';
    }
}
