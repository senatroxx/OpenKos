<?php

namespace App\Business\Reminders;

use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentReminderScheduler
{
    /** @return array<ReminderEvent> */
    public function pendingFor(Lease $lease, ReminderSettings $settings): array
    {
        $invoices = $lease->invoices()
            ->payable()
            ->orderBy('period_start')
            ->get([
                'id',
                'lease_id',
                'reference',
                'period_start',
                'period_end',
                'due_date',
                'status',
                'total',
                'amount_paid',
                'currency',
            ]);

        return $this->eventsFor($lease, $invoices, $settings);
    }

    /**
     * @param  Collection<int, Lease>  $leases
     * @return array<int, array<int, ReminderEvent>>
     */
    public function pendingForMany(Collection $leases, ReminderSettings $settings): array
    {
        if ($leases->isEmpty()) {
            return [];
        }

        $invoicesByLease = Invoice::query()
            ->whereIn('lease_id', $leases->modelKeys())
            ->payable()
            ->orderBy('period_start')
            ->get([
                'id',
                'lease_id',
                'reference',
                'period_start',
                'period_end',
                'due_date',
                'status',
                'total',
                'amount_paid',
                'currency',
            ])
            ->groupBy('lease_id');

        $eventsByLease = [];

        foreach ($leases as $lease) {
            $eventsByLease[$lease->getKey()] = $this->eventsFor(
                $lease,
                $invoicesByLease->get($lease->getKey(), []),
                $settings,
            );
        }

        return $eventsByLease;
    }

    /**
     * @param  iterable<Invoice>  $invoices
     * @return array<int, ReminderEvent>
     */
    private function eventsFor(Lease $lease, iterable $invoices, ReminderSettings $settings): array
    {
        $today = now()->startOfDay();
        $events = [];

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
            $amount = $invoice->outstanding;
            $currency = $invoice->currency;
            $periodStart = $invoice->period_start->toDateString();
            $periodEnd = $invoice->period_end->toDateString();
            $dueDateStr = $dueDate->toDateString();

            $status = $dueDate->lessThan($today)
                ? 'overdue'
                : ($dueDate->greaterThan($today) ? 'upcoming' : 'due');

            match ($status) {
                'upcoming' => $this->collectUpcoming($events, $lease, $invoice, $periodStart, $periodEnd, $dueDateStr, $amount, $currency, $dueDate, $today, $settings),
                'due' => $this->collectDueToday($events, $lease, $invoice, $periodStart, $periodEnd, $dueDateStr, $amount, $currency, $dueDate, $today),
                'overdue' => $this->collectOverdue($events, $lease, $invoice, $periodStart, $periodEnd, $dueDateStr, $amount, $currency, $dueDate, $today, $settings),
            };
        }

        return $events;
    }

    private function collectUpcoming(
        array &$events,
        Lease $lease,
        Invoice $invoice,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        string $amount,
        string $currency,
        CarbonInterface $dueDate,
        CarbonInterface $today,
        ReminderSettings $settings,
    ): void {
        $daysUntil = (int) $today->diffInDays($dueDate, false);

        if ($daysUntil === $settings->daysBefore) {
            $events[] = new ReminderEvent(
                lease: $lease,
                type: ReminderType::Upcoming,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                dueDate: $dueDateStr,
                amount: $amount,
                currency: $currency,
                invoice: $invoice,
            );
        }
    }

    private function collectDueToday(
        array &$events,
        Lease $lease,
        Invoice $invoice,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        string $amount,
        string $currency,
        CarbonInterface $dueDate,
        CarbonInterface $today,
    ): void {
        if ($today->eq($dueDate)) {
            $events[] = new ReminderEvent(
                lease: $lease,
                type: ReminderType::DueToday,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                dueDate: $dueDateStr,
                amount: $amount,
                currency: $currency,
                invoice: $invoice,
            );
        }
    }

    private function collectOverdue(
        array &$events,
        Lease $lease,
        Invoice $invoice,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        string $amount,
        string $currency,
        CarbonInterface $dueDate,
        CarbonInterface $today,
        ReminderSettings $settings,
    ): void {
        $overdueDays = (int) $dueDate->diffInDays($today, false);

        foreach ($settings->overdueIntervals as $interval) {
            if ($overdueDays >= $interval) {
                $events[] = new ReminderEvent(
                    lease: $lease,
                    type: ReminderType::Overdue,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    dueDate: $dueDateStr,
                    amount: $amount,
                    currency: $currency,
                    overdueDays: $interval,
                    invoice: $invoice,
                );
            }
        }
    }
}
