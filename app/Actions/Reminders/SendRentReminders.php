<?php

namespace App\Actions\Reminders;

use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderSettings;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Lease;
use App\Models\Setting;
use App\Repositories\ReminderRepository;
use Illuminate\Database\Eloquent\Collection;

class SendRentReminders
{
    private const CHUNK_SIZE = 100;

    public function __construct(
        private PaymentReminderScheduler $scheduler,
        private ReminderRepository $repository,
    ) {}

    public function execute(?Lease $lease = null): int
    {
        $settings = new ReminderSettings(
            enabled: Setting::get('reminder_enabled') ?? true,
            daysBefore: Setting::get('reminder_days_before') ?? 3,
            overdueIntervals: Setting::get('reminder_overdue_intervals') ?? [1, 3, 7],
        );

        if (! $settings->enabled) {
            return 0;
        }

        $channels = Setting::get('reminder_channels') ?? ['log'];

        if ($lease) {
            $lease->load(['primaryTenant.user', 'unit']);

            return $this->processLease($lease, $settings, $channels);
        }

        $sent = 0;

        Lease::active()
            ->with(['primaryTenant.user', 'unit'])
            ->chunkById(
                self::CHUNK_SIZE,
                function (Collection $leases) use (&$sent, $settings, $channels): void {
                    $sent += $this->processLeaseChunk($leases, $settings, $channels);
                },
                'id',
            );

        return $sent;
    }

    private function processLease(Lease $lease, ReminderSettings $settings, array $channels): int
    {
        if (! $lease->primaryTenant?->hasReminderRoute($channels)) {
            return 0;
        }

        return $this->recordEvents(
            $this->scheduler->pendingFor($lease, $settings),
            $channels,
        );
    }

    /** @param  Collection<int, Lease>  $leases */
    private function processLeaseChunk(Collection $leases, ReminderSettings $settings, array $channels): int
    {
        $eligibleLeases = $leases
            ->filter(fn (Lease $lease): bool => $lease->primaryTenant?->hasReminderRoute($channels))
            ->values();

        if ($eligibleLeases->isEmpty()) {
            return 0;
        }

        $eventsByLease = $this->scheduler->pendingForMany($eligibleLeases, $settings);
        $sent = 0;

        foreach ($eligibleLeases as $lease) {
            $sent += $this->recordEvents(
                $eventsByLease[$lease->getKey()] ?? [],
                $channels,
            );
        }

        return $sent;
    }

    private function recordEvents(iterable $events, array $channels): int
    {
        $sent = 0;

        foreach ($events as $event) {
            $log = $this->repository->recordIfAbsent($event, $channels);

            if (! $log) {
                continue;
            }

            InvoiceReminderDispatched::dispatch($event);
            $sent++;
        }

        return $sent;
    }
}
