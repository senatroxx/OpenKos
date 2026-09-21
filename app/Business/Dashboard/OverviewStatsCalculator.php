<?php

namespace App\Business\Dashboard;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OverviewStatsCalculator
{
    /** @param array{active_leases: Collection<int, object>, payments: Collection<int, object>, invoices: Collection<int, object>} $data */
    public function computeFinance(array $data, CarbonInterface $now): array
    {
        $monthlyPotential = $this->aggregate(
            $data['active_leases'],
            fn ($row): string => (string) $row->rent_amount,
        );

        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        $periodStart = Carbon::create($currentYear, $currentMonth, 1)->toDateString();
        $periodEnd = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->toDateString();

        $revenueThisMonth = $data['payments'];
        $outstanding = $data['invoices'];
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
     * @param  Collection<int, int>  $accessiblePropertyIds
     * @return array{this_month: array<int, array{currency: string, amount: string}>, last_month: array<int, array{currency: string, amount: string}>, change_vs_last_month: array<int, array{currency: string, amount: string}>}
     */
    /** @param Collection<int, object> $expenses */
    public function computeExpenses(Collection $expenses, CarbonInterface $now): array
    {
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonthNoOverflow();

        $thisMonth = $this->aggregate(
            $expenses->filter(fn (object $expense): bool => Carbon::parse($expense->expense_date)->betweenIncluded($thisMonthStart, $thisMonthStart->copy()->endOfMonth())
            ),
            fn (object $expense): string => (string) $expense->amount,
        );
        $lastMonth = $this->aggregate(
            $expenses->filter(fn (object $expense): bool => Carbon::parse($expense->expense_date)->betweenIncluded($lastMonthStart, $thisMonthStart->copy()->subDay())
            ),
            fn (object $expense): string => (string) $expense->amount,
        );

        $currencies = collect([...$thisMonth, ...$lastMonth])
            ->pluck('currency')
            ->unique()
            ->sort()
            ->values();
        $thisMonth = $this->completeAmountGroups($thisMonth, $currencies);
        $lastMonth = $this->completeAmountGroups($lastMonth, $currencies);
        $thisMonthByCurrency = collect($thisMonth)->keyBy('currency');
        $lastMonthByCurrency = collect($lastMonth)->keyBy('currency');
        $change = $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => BigDecimal::of($thisMonthByCurrency->get($currency)['amount'])
                ->minus($lastMonthByCurrency->get($currency)['amount'])
                ->toString(),
        ])->all();

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'change_vs_last_month' => $change,
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
