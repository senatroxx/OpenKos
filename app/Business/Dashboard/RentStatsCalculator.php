<?php

namespace App\Business\Dashboard;

use App\Data\Dashboard\RentLeaseData;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

final class RentStatsCalculator
{
    /**
     * @param  array<int, RentLeaseData>  $leases
     * @param  array<int, int>  $paidLeaseIds
     */
    public function computeStats(array $leases, array $paidLeaseIds, int $today): array
    {
        $paidSet = array_fill_keys($paidLeaseIds, true);
        $overdueCount = 0;
        $overdueAmounts = [];
        $dueTodayCount = 0;
        $dueSoonCount = 0;
        $paidCount = 0;

        foreach ($leases as $lease) {
            if (isset($paidSet[$lease->id])) {
                $paidCount++;

                continue;
            }

            if ($lease->rentDueDay < $today) {
                $overdueCount++;
                $overdueAmounts[$lease->currency] = ($overdueAmounts[$lease->currency] ?? BigDecimal::zero())
                    ->plus($lease->rentAmount);
            } elseif ($lease->rentDueDay === $today) {
                $dueTodayCount++;
            } elseif ($lease->rentDueDay <= $today + 7) {
                $dueSoonCount++;
            }
        }

        $amounts = [];
        foreach ($overdueAmounts as $currency => $amount) {
            $amounts[] = ['currency' => $currency, 'amount' => $amount->toString()];
        }

        return [
            'overdue' => ['count' => $overdueCount, 'amounts' => $amounts],
            'due_today' => $dueTodayCount,
            'due_soon' => $dueSoonCount,
            'paid' => $paidCount,
        ];
    }

    public function transformEntry(RentLeaseData $lease, int $today, CarbonInterface $now): ?array
    {
        if ($lease->hasPayment) {
            $status = 'paid';
            $daysOverdue = null;
        } elseif ($lease->rentDueDay < $today) {
            $status = 'overdue';
            $dueDate = $now->copy()->setDay(min($lease->rentDueDay, $now->daysInMonth));
            $daysOverdue = (int) $dueDate->diffInDays($now, false);
        } elseif ($lease->rentDueDay === $today) {
            $status = 'due_today';
            $daysOverdue = null;
        } elseif ($lease->rentDueDay <= $today + 7) {
            $status = 'due_soon';
            $daysOverdue = null;
        } else {
            return null;
        }

        return [
            'id' => $lease->id,
            'tenant_name' => $lease->tenantName,
            'target_type' => $lease->targetType,
            'unit_name' => $lease->unitName,
            'property_name' => $lease->propertyName,
            'rent_due_day' => $lease->rentDueDay,
            'days_overdue' => $daysOverdue,
            'rent_amount' => $lease->rentAmount,
            'currency' => $lease->currency,
            'rent_status' => $status,
        ];
    }
}
