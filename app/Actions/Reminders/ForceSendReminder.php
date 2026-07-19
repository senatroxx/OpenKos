<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Lease;
use App\Models\Setting;
use App\Repositories\ReminderRepository;
use Carbon\Carbon;

class ForceSendReminder
{
    public function __construct(
        private PaymentReminderScheduler $scheduler,
        private ReminderRepository $repository,
    ) {}

    public function execute(Lease $lease): string
    {
        $lease->load(['primaryTenant.user']);
        $tenant = $lease->primaryTenant;

        $channels = Setting::get('reminder_channels') ?? ['log'];
        $hasContact = $tenant?->phone || ($tenant?->user?->email && in_array('mail', $channels));

        if (! $hasContact) {
            return 'no_contact';
        }

        $settings = new ReminderSettings(
            enabled: true,
            daysBefore: Setting::get('reminder_days_before') ?? 3,
            overdueIntervals: Setting::get('reminder_overdue_intervals') ?? [1, 3, 7],
        );

        // Try scheduled events first — send the first one not already logged.
        foreach ($this->scheduler->pendingFor($lease, $settings) as $event) {
            $log = $this->repository->recordIfAbsent($event, $channels);

            if ($log) {
                InvoiceReminderDispatched::dispatch($event);

                return 'sent';
            }
        }

        // ponytail: fallback when no event is scheduled (e.g. invoice due
        // outside daysBefore window). Build a reminder for the first payable
        // invoice so manual "Send Reminder" always works.
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

        if ($log) {
            InvoiceReminderDispatched::dispatch($event);

            return 'sent';
        }

        return 'already_sent';
    }
}
