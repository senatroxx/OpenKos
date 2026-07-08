<?php

namespace App\Business\Reminders;

use App\Data\Reminder\ReminderEvent;
use App\Data\Reminder\ReminderSettings;
use App\Enums\ReminderType;
use App\Models\Lease;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PaymentReminderScheduler
{
    /** @return array<ReminderEvent> */
    public function pendingFor(Lease $lease, ReminderSettings $settings): array
    {
        $invoices = $lease->invoices()->payable()->orderBy('period_start')->get();
        $today = now()->startOfDay();
        $events = [];

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
            $amount = (int) ((float) $invoice->outstanding * 100);
            $periodStart = $invoice->period_start->toDateString();
            $periodEnd = $invoice->period_end->toDateString();
            $dueDateStr = $dueDate->toDateString();

            $status = $dueDate->lessThan($today)
                ? 'overdue'
                : ($invoice->period_start->isFuture() ? 'upcoming' : 'due');

            match ($status) {
                'upcoming' => $this->collectUpcoming($events, $lease, $periodStart, $periodEnd, $dueDateStr, $amount, $dueDate, $today, $settings),
                'due' => $this->collectDueToday($events, $lease, $periodStart, $periodEnd, $dueDateStr, $amount, $dueDate, $today),
                'overdue' => $this->collectOverdue($events, $lease, $periodStart, $periodEnd, $dueDateStr, $amount, $dueDate, $today, $settings),
            };
        }

        return $events;
    }

    private function collectUpcoming(
        array &$events,
        Lease $lease,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        int $amount,
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
            );
        }
    }

    private function collectDueToday(
        array &$events,
        Lease $lease,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        int $amount,
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
            );
        }
    }

    private function collectOverdue(
        array &$events,
        Lease $lease,
        string $periodStart,
        string $periodEnd,
        string $dueDateStr,
        int $amount,
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
                    overdueDays: $interval,
                );
            }
        }
    }
}
