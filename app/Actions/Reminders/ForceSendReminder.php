<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderInvoiceData;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Setting;
use App\Repositories\ReminderRepository;
use App\Services\Localization\ApplicationLocale;

class ForceSendReminder
{
    public function __construct(
        private PaymentReminderScheduler $scheduler,
        private ReminderRepository $repository,
        private ApplicationLocale $locale,
    ) {}

    public function execute(Lease $lease): string
    {
        $this->locale->apply();
        $lease->load(['primaryTenant.user', 'property', 'unit']);
        $tenant = $lease->primaryTenant;

        $channels = Setting::get('reminder_channels') ?? ['log'];

        if (! $tenant?->hasReminderRoute($channels)) {
            return 'no_contact';
        }

        $settings = new ReminderSettings(
            enabled: true,
            daysBefore: Setting::get('reminder_days_before') ?? 3,
            overdueIntervals: Setting::get('reminder_overdue_intervals') ?? [1, 3, 7],
        );

        // Try scheduled events first — send the first one not already logged.
        $invoices = $this->repository->payableInvoicesFor($lease)
            ->map(fn (Invoice $invoice): ReminderInvoiceData => ReminderInvoiceData::fromInvoice($invoice));

        foreach ($this->scheduler->pendingFor($lease, $invoices->all(), $settings, today()) as $event) {
            $log = $this->repository->recordIfAbsent($event, $channels);

            if ($log) {
                InvoiceReminderDispatched::dispatch($event);

                return 'sent';
            }
        }

        // ponytail: fallback when no event is scheduled (e.g. invoice due
        // outside daysBefore window). Build a reminder for the first payable
        // invoice so manual "Send Reminder" always works.
        $invoice = $invoices->first();

        if (! $invoice) {
            return 'all_paid';
        }

        $today = today();
        $dueDate = $invoice->dueDate;
        $overdueDays = $dueDate->lessThan($today) ? (int) $dueDate->diffInDays($today) : null;

        $type = match (true) {
            $overdueDays !== null => ReminderType::Overdue,
            $today->eq($dueDate) => ReminderType::DueToday,
            default => ReminderType::Upcoming,
        };

        $event = new ReminderEvent(
            lease: $lease,
            type: $type,
            periodStart: $invoice->periodStart,
            periodEnd: $invoice->periodEnd,
            dueDate: $invoice->dueDate->toDateString(),
            amount: $invoice->amount,
            currency: $invoice->currency,
            overdueDays: $overdueDays,
            invoice: $invoice->invoice,
        );

        $log = $this->repository->recordIfAbsent($event, $channels);

        if ($log) {
            InvoiceReminderDispatched::dispatch($event);

            return 'sent';
        }

        return 'already_sent';
    }
}
