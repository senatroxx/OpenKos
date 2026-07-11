<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderSettings;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Lease;
use App\Models\Setting;
use App\Repositories\ReminderRepository;

class ForceSendReminder
{
    public function __construct(
        private PaymentReminderScheduler $scheduler,
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

        $settings = new ReminderSettings(
            enabled: true,
            daysBefore: Setting::get('reminder_days_before') ?? 3,
            overdueIntervals: Setting::get('reminder_overdue_intervals') ?? [1, 3, 7],
        );

        $events = $this->scheduler->pendingFor($lease, $settings);

        if (empty($events)) {
            return 'all_paid';
        }

        $event = $events[0];
        $log = $this->repository->recordIfAbsent($event, $channels);

        if (! $log) {
            return 'already_sent';
        }

        $invoice = $lease->invoices()
            ->payable()
            ->whereDate('period_start', $event->periodStart)
            ->first();

        InvoiceReminderDispatched::dispatch($event, $invoice);

        return 'sent';
    }
}
