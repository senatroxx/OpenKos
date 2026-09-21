<?php

namespace App\Business\Reminders;

use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderInvoiceData;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Models\Lease;
use Carbon\CarbonInterface;

class PaymentReminderScheduler
{
    /**
     * @param  iterable<int, ReminderInvoiceData>  $invoices
     * @return array<int, ReminderEvent>
     */
    public function pendingFor(
        Lease $lease,
        iterable $invoices,
        ReminderSettings $settings,
        CarbonInterface $today,
    ): array {
        $today = $today->copy()->startOfDay();
        $events = [];

        foreach ($invoices as $invoice) {
            $dueDate = $invoice->dueDate;
            $amount = $invoice->amount;
            $currency = $invoice->currency;
            $periodStart = $invoice->periodStart;
            $periodEnd = $invoice->periodEnd;
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
        ReminderInvoiceData $invoice,
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
                invoice: $invoice->invoice,
            );
        }
    }

    private function collectDueToday(
        array &$events,
        Lease $lease,
        ReminderInvoiceData $invoice,
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
                invoice: $invoice->invoice,
            );
        }
    }

    private function collectOverdue(
        array &$events,
        Lease $lease,
        ReminderInvoiceData $invoice,
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
                    invoice: $invoice->invoice,
                );
            }
        }
    }
}
