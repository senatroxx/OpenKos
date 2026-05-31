<?php

namespace App\Services;

use App\Enums\BillingUnit;
use App\Models\Lease;
use Carbon\Carbon;

class RentScheduleService
{
    /**
     * @return array<int, array{start: Carbon, end: Carbon, label: string, amount: string}>
     */
    public function generatePeriods(Lease $lease, Carbon $from, ?Carbon $to = null, int $limit = 12): array
    {
        $periods = [];
        $interval = $lease->billing_interval ?? 1;
        $unit = $lease->billing_unit ?? BillingUnit::Month;

        $current = $from->copy();
        $labelIndex = 1;

        while (count($periods) < $limit) {
            $next = $this->advancePeriod($current, $interval, $unit);

            if ($to && $current->greaterThanOrEqualTo($to)) {
                break;
            }

            $periodEnd = ($to && $next->greaterThan($to)) ? $to->copy() : $next->copy();

            $periods[] = [
                'start' => $current->copy(),
                'end' => $periodEnd,
                'label' => $this->periodLabel($labelIndex, $interval, $unit),
                'amount' => $lease->rent_amount ?? '0',
            ];

            $current = $next;
            $labelIndex++;
        }

        return $periods;
    }

    private function advancePeriod(Carbon $from, int $interval, BillingUnit $unit): Carbon
    {
        return match ($unit) {
            BillingUnit::Day => $from->copy()->addDays($interval),
            BillingUnit::Week => $from->copy()->addWeeks($interval),
            BillingUnit::Month => $from->copy()->addMonthsNoOverflow($interval),
            BillingUnit::Year => $from->copy()->addYears($interval),
        };
    }

    private function periodLabel(int $index, int $interval, BillingUnit $unit): string
    {
        return match ($unit) {
            BillingUnit::Day => "Day {$index}",
            BillingUnit::Week => "Week {$index}",
            BillingUnit::Month => $interval === 1
                ? "Month {$index}"
                : ($interval === 3 ? "Quarter {$index}" : ($interval === 6 ? "Semester {$index}" : "Period {$index}")),
            BillingUnit::Year => "Year {$index}",
        };
    }
}
