<?php

namespace App\Business\Dashboard;

use App\Models\Lease;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OverviewStatsCalculator
{
    public function computeFinance(Builder $activeLeasesQuery): array
    {
        $monthlyPotential = (clone $activeLeasesQuery)->sum('rent_amount');

        $now = now();
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        $leaseIds = (clone $activeLeasesQuery)->pluck('id');

        $periodStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
        $periodEnd = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->endOfDay();

        $revenueThisMonth = Payment::where('paymentable_type', Lease::class)
            ->where('status', 'confirmed')
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->whereHasMorph('paymentable', [Lease::class], fn (Builder $q) => $q->whereIn('id', $leaseIds))
            ->sum('amount');

        $paidIds = Payment::where('paymentable_type', Lease::class)
            ->where('status', 'confirmed')
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->whereHasMorph('paymentable', [Lease::class], fn (Builder $q) => $q->whereIn('id', $leaseIds))
            ->distinct('paymentable_id')
            ->pluck('paymentable_id');

        $outstanding = (clone $activeLeasesQuery)
            ->whereNotIn('id', $paidIds)
            ->sum('rent_amount');

        $collectionRate = $monthlyPotential > 0
            ? round(($revenueThisMonth / $monthlyPotential) * 100)
            : 0;

        return [
            'revenue_this_month' => (int) $revenueThisMonth,
            'monthly_potential' => (int) $monthlyPotential,
            'outstanding' => (int) $outstanding,
            'collection_rate' => $collectionRate,
        ];
    }
}
