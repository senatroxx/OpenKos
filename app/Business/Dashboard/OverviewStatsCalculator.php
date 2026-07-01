<?php

namespace App\Business\Dashboard;

use App\Models\Lease;
use App\Models\Payment;
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

        $revenueThisMonth = Payment::where('paymentable_type', Lease::class)
            ->where('status', 'confirmed')
            ->whereMonth('period_start', $currentMonth)
            ->whereYear('period_start', $currentYear)
            ->whereHasMorph('paymentable', [Lease::class], fn (Builder $q) => $q->whereIn('id', $leaseIds))
            ->sum('amount');

        $paidIds = Payment::where('paymentable_type', Lease::class)
            ->where('status', 'confirmed')
            ->whereMonth('period_start', $currentMonth)
            ->whereYear('period_start', $currentYear)
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
