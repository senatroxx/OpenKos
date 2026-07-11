<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderSettings;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Lease;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Repositories\ReminderRepository;
use Illuminate\Support\Collection;

class SendRentReminders
{
    public function __construct(
        private PaymentReminderScheduler $scheduler,
        private ReminderRepository $repository,
    ) {}

    /** @return Collection<int, ReminderLog> */
    public function execute(?Lease $lease = null): Collection
    {
        $settings = new ReminderSettings(
            enabled: Setting::get('reminder_enabled') ?? true,
            daysBefore: Setting::get('reminder_days_before') ?? 3,
            overdueIntervals: Setting::get('reminder_overdue_intervals') ?? [1, 3, 7],
        );

        if (! $settings->enabled) {
            return collect();
        }

        $leases = $lease
            ? [$lease->load(['primaryTenant'])]
            : Lease::active()->with(['primaryTenant'])->get();

        $sent = collect();

        $channels = Setting::get('reminder_channels') ?? ['log'];

        foreach ($leases as $lease) {
            $tenant = $lease->primaryTenant;
            $hasContact = $tenant?->phone || ($tenant?->email && in_array('mail', $channels));
            if (! $hasContact) {
                continue;
            }

            // Index payable invoices by period_start for O(1) lookup.
            $invoices = $lease->invoices()
                ->payable()
                ->get()
                ->keyBy(fn ($i) => $i->period_start->toDateString());

            foreach ($this->scheduler->pendingFor($lease, $settings) as $event) {
                $log = $this->repository->recordIfAbsent($event, $channels);

                if (! $log) {
                    continue;
                }

                InvoiceReminderDispatched::dispatch($event, $invoices->get($event->periodStart));
                $sent->push($log);
            }
        }

        return $sent;
    }
}
