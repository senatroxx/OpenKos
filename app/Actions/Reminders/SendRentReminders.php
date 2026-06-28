<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderSettings;
use App\Models\Lease;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Notifications\RentReminder;
use App\Repositories\ReminderRepository;
use Illuminate\Support\Collection;

class SendRentReminders
{
    public function __construct(
        private PaymentReminderScheduler $scheduler,
        private ReminderRepository $repository,
        private Setting $settings,
    ) {}

    /** @return Collection<int, ReminderLog> */
    public function execute(?Lease $lease = null): Collection
    {
        $settings = ReminderSettings::fromSetting($this->settings->get());
        if (! $settings->enabled) {
            return collect();
        }

        $leases = $lease
            ? [$lease->load(['primaryTenant', 'payments'])]
            : Lease::active()->with(['primaryTenant', 'payments'])->get();

        $sent = collect();

        foreach ($leases as $lease) {
            $tenant = $lease->primaryTenant;
            if (! $tenant?->phone) {
                continue;
            }

            foreach ($this->scheduler->pendingFor($lease, $settings) as $event) {
                $log = $this->repository->recordIfAbsent($event);

                if (! $log) {
                    continue;
                }

                $tenant->notify(new RentReminder($event));
                $sent->push($log);
            }
        }

        return $sent;
    }
}
