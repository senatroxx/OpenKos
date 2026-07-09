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
    ) {}

    public function execute(Lease $lease): string
    {
        $lease->load(['primaryTenant']);
        $tenant = $lease->primaryTenant;

        $channels = Setting::get('reminder_channels') ?? ['log'];
        $hasContact = $tenant?->phone || ($tenant?->email && in_array('mail', $channels));

        if (! $hasContact) {
            return 'no_contact';
        }

        $invoice = $lease->invoices()->payable()->orderBy('period_start')->first();

        if (! $invoice) {
            return 'all_paid';
        }

        $today = now()->startOfDay();
        $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
        $overdueDays = $dueDate->lessThan($today) ? (int) $dueDate->diffInDays($today) : null;

        $type = match (true) {
            $overdueDays !== null => ReminderType::Overdue,
            $today->eq($dueDate) => ReminderType::DueToday,
            default => ReminderType::Upcoming,
        };

        $event = new ReminderEvent(
            lease: $lease,
            type: $type,
            periodStart: $invoice->period_start->toDateString(),
            periodEnd: $invoice->period_end->toDateString(),
            dueDate: $invoice->due_date->toDateString(),
            amount: (int) round((float) $invoice->outstanding * 100),
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
