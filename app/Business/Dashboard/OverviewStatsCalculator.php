<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

        $revenueThisMonth = Payment::where('status', 'confirmed')
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereBetween('period_start', [$periodStart, $periodEnd])
                ->whereIn('lease_id', $leaseIds))
            ->sum('amount');

        $outstanding = (float) Invoice::whereIn('lease_id', $leaseIds)
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->sum(DB::raw('total - amount_paid'));

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
