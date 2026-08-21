<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OverviewStatsCalculator
{
    public function computeFinance(Builder $activeLeasesQuery): array
    {
        $monthlyPotential = $this->aggregate(
            (clone $activeLeasesQuery)->get(['rent_amount', 'currency']),
            fn ($row): string => (string) $row->rent_amount,
        );

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
            ->get(['amount', 'currency']);

        $outstanding = Invoice::whereIn('lease_id', $leaseIds)
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->get(['total', 'amount_paid', 'currency']);
        $revenueThisMonth = $this->aggregate($revenueThisMonth, fn ($row): string => (string) $row->amount);
        $outstanding = $this->aggregate(
            $outstanding,
            fn ($row): string => BigDecimal::of((string) $row->total)->minus((string) $row->amount_paid)->toString(),
        );

        $currencies = collect([
            ...$monthlyPotential,
            ...$revenueThisMonth,
            ...$outstanding,
        ])->pluck('currency')->unique()->values();

        $monthlyPotential = $this->completeAmountGroups($monthlyPotential, $currencies);
        $revenueThisMonth = $this->completeAmountGroups($revenueThisMonth, $currencies);
        $outstanding = $this->completeAmountGroups($outstanding, $currencies);

        $potentialByCurrency = collect($monthlyPotential)->keyBy('currency');
        $revenueByCurrency = collect($revenueThisMonth)->keyBy('currency');
        $collectionRate = $currencies->map(function (string $currency) use ($potentialByCurrency, $revenueByCurrency): array {
            $potential = $potentialByCurrency->get($currency);
            $revenue = $revenueByCurrency->get($currency);
            $rate = $revenue && $potential && BigDecimal::of($potential['amount'])->isPositive()
                ? BigDecimal::of($revenue['amount'])
                    ->dividedBy($potential['amount'], 4, RoundingMode::HalfUp)
                    ->multipliedBy(100)
                    ->toScale(0, RoundingMode::HalfUp)
                    ->toInt()
                : 0;

            return ['currency' => $currency, 'rate' => $rate];
        })->all();

        return [
            'revenue_this_month' => $revenueThisMonth,
            'monthly_potential' => $monthlyPotential,
            'outstanding' => $outstanding,
            'collection_rate' => $collectionRate,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{currency: string, amount: string}>
     */
    private function aggregate(Collection $rows, callable $amount): array
    {
        return $rows
            ->groupBy(fn ($row): string => (string) $row->currency)
            ->map(function ($rows, string $currency) use ($amount): array {
                $total = $rows->reduce(
                    fn (BigDecimal $total, $row): BigDecimal => $total->plus($amount($row) ?: '0'),
                    BigDecimal::zero(),
                );

                return ['currency' => $currency, 'amount' => $total->toString()];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{currency: string, amount: string}>  $groups
     * @param  Collection<int, string>  $currencies
     * @return array<int, array{currency: string, amount: string}>
     */
    private function completeAmountGroups(array $groups, Collection $currencies): array
    {
        $groupsByCurrency = collect($groups)->keyBy('currency');

        return $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => ($groupsByCurrency->get($currency) ?? ['amount' => BigDecimal::zero()->toString()])['amount'],
        ])->all();
    }
}
