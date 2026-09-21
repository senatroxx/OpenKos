<?php

namespace App\Business\Dashboard;

use App\Data\Dashboard\FinancialDashboardData;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FinancialDashboardCalculator
{
    /**
     * @param  Collection<int, array{id: int, name: string}>  $accessibleProperties
     * @return array<string, mixed>
     */
    public function calculate(Collection $accessibleProperties, FinancialDashboardData $data, ?int $selectedPropertyId, string $period, CarbonInterface $now): array
    {
        $propertyIds = $accessibleProperties->pluck('id');

        if ($selectedPropertyId !== null) {
            $propertyIds = collect([$selectedPropertyId]);
        }

        $periodStart = $period === 'ytd'
            ? $now->copy()->startOfYear()
            : $now->copy()->startOfMonth();
        $periodEnd = $now->copy()->endOfDay();
        $trendStart = $now->copy()->startOfMonth()->subMonthsNoOverflow(11);

        $invoiceRows = $data->invoiceRows;
        $expenseRows = $data->expenseRows;
        $paymentRows = $data->paymentRows;
        $allocationRows = $data->allocationRows;
        $upcomingRows = $data->upcomingRows;

        $selectedInvoices = $this->rowsInPeriod($invoiceRows, $periodStart, $periodEnd);
        $selectedExpenses = $this->rowsInPeriod($expenseRows, $periodStart, $periodEnd);
        $revenue = $this->amountGroups($selectedInvoices, 'revenue');
        $expenses = $this->amountGroups($selectedExpenses, 'expenses');
        $noi = $this->subtractGroups($revenue, $expenses);
        $billed = $revenue;
        $collected = $this->completeAmountGroups($this->amountGroups($allocationRows, 'collected'), $billed);
        $outstanding = $this->amountGroups($selectedInvoices, 'outstanding_amount');
        $occupancy = $this->occupancy($data->occupancyProperties);

        return [
            'period' => $period,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'overview' => [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'noi' => $noi,
                'operating_margin' => $this->percentageGroups($noi, $revenue),
            ],
            'collections' => [
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'collection_rate' => $this->percentageGroups($collected, $billed),
            ],
            'cash_flow' => $this->monthlyGroups($paymentRows, 'collected', $expenseRows, 'expenses', $periodStart, $periodEnd),
            'trends' => $this->monthlyGroups($invoiceRows, 'revenue', $expenseRows, 'expenses', $trendStart, $now),
            'property_performance' => $this->propertyPerformance(
                $accessibleProperties,
                $propertyIds,
                $selectedInvoices,
                $selectedExpenses,
                $allocationRows,
                $occupancy['properties'],
            ),
            'occupancy' => $occupancy,
            'expense_breakdown' => $this->expenseBreakdown($selectedExpenses),
            'upcoming_receivables' => $this->monthlyAmountGroups($upcomingRows, 'receivable', $now),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function rowsInPeriod(Collection $rows, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $rows->filter(function (object $row) use ($start, $end): bool {
            $month = Carbon::parse($row->month);

            return $month->betweenIncluded($start->copy()->startOfMonth(), $end->copy()->startOfMonth());
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{currency: string, amount: string}>
     */
    private function amountGroups(Collection $rows, string $amountColumn): array
    {
        return $rows
            ->groupBy(fn (object $row): string => (string) $row->currency)
            ->map(function (Collection $currencyRows, string $currency) use ($amountColumn): array {
                $amount = $currencyRows->reduce(
                    fn (BigDecimal $total, object $row): BigDecimal => $total->plus((string) ($row->{$amountColumn} ?? '0')),
                    BigDecimal::zero(),
                );

                return ['currency' => $currency, 'amount' => $amount->toString()];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{currency: string, amount: string}>  $left
     * @param  array<int, array{currency: string, amount: string}>  $right
     * @return array<int, array{currency: string, amount: string}>
     */
    private function subtractGroups(array $left, array $right): array
    {
        $leftByCurrency = collect($left)->keyBy('currency');
        $rightByCurrency = collect($right)->keyBy('currency');
        $currencies = $leftByCurrency->keys()->merge($rightByCurrency->keys())->unique()->sort();

        return $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => BigDecimal::of((string) ($leftByCurrency->get($currency)['amount'] ?? '0'))
                ->minus((string) ($rightByCurrency->get($currency)['amount'] ?? '0'))
                ->toString(),
        ])->values()->all();
    }

    /**
     * @param  array<int, array{currency: string, amount: string}>  $numerators
     * @param  array<int, array{currency: string, amount: string}>  $denominators
     * @return array<int, array{currency: string, rate: string}>
     */
    private function percentageGroups(array $numerators, array $denominators): array
    {
        $numeratorsByCurrency = collect($numerators)->keyBy('currency');
        $denominatorsByCurrency = collect($denominators)->keyBy('currency');
        $currencies = $denominatorsByCurrency
            ->filter(fn (array $group): bool => BigDecimal::of((string) $group['amount'])->isPositive())
            ->keys()
            ->sort();

        return $currencies->map(function (string $currency) use ($numeratorsByCurrency, $denominatorsByCurrency): array {
            $numerator = BigDecimal::of((string) ($numeratorsByCurrency->get($currency)['amount'] ?? '0'));
            $denominator = BigDecimal::of((string) ($denominatorsByCurrency->get($currency)['amount'] ?? '0'));

            return [
                'currency' => $currency,
                'rate' => $denominator->isPositive()
                    ? $numerator->dividedBy($denominator, 6, RoundingMode::HalfUp)->multipliedBy(100)->toScale(1, RoundingMode::HalfUp)->toString()
                    : '0.0',
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, object>  $leftRows
     * @param  Collection<int, object>  $rightRows
     * @return array<int, array<string, mixed>>
     */
    private function monthlyGroups(
        Collection $leftRows,
        string $leftColumn,
        Collection $rightRows,
        string $rightColumn,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        $startMonth = $start->copy()->startOfMonth();
        $monthCount = $startMonth->diffInMonths($end->copy()->startOfMonth());
        $months = collect(range(0, $monthCount))->map(fn (int $offset): CarbonInterface => $startMonth->copy()->addMonthsNoOverflow($offset));
        $left = $this->monthlyAmountMap($leftRows, $leftColumn);
        $right = $this->monthlyAmountMap($rightRows, $rightColumn);

        return $months->map(function (CarbonInterface $month) use ($left, $right, $leftColumn, $rightColumn): array {
            $monthKey = $month->toDateString();
            $leftGroups = $this->mapToGroups($left[$monthKey] ?? []);
            $rightGroups = $this->mapToGroups($right[$monthKey] ?? []);
            $result = [
                'month' => $monthKey,
                'label' => $month->format('M Y'),
                $leftColumn => $leftGroups,
                $rightColumn => $rightGroups,
            ];

            if ($leftColumn === 'revenue' && $rightColumn === 'expenses') {
                $result['noi'] = $this->subtractGroups($leftGroups, $rightGroups);
            }

            return $result;
        })->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array<string, string>>
     */
    private function monthlyAmountMap(Collection $rows, string $amountColumn): array
    {
        $map = [];

        foreach ($rows as $row) {
            $month = (string) $row->month;
            $currency = (string) $row->currency;
            $map[$month][$currency] = isset($map[$month][$currency])
                ? BigDecimal::of($map[$month][$currency])->plus((string) ($row->{$amountColumn} ?? '0'))->toString()
                : BigDecimal::of((string) ($row->{$amountColumn} ?? '0'))->toString();
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $amounts
     * @return array<int, array{currency: string, amount: string}>
     */
    private function mapToGroups(array $amounts): array
    {
        return collect($amounts)
            ->sortKeys()
            ->map(fn (string $amount, string $currency): array => ['currency' => $currency, 'amount' => $amount])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{currency: string, amount: string}>  $amounts
     * @param  array<int, array{currency: string, amount: string}>  $reference
     * @return array<int, array{currency: string, amount: string}>
     */
    private function completeAmountGroups(array $amounts, array $reference): array
    {
        $amountsByCurrency = collect($amounts)->keyBy('currency');
        $currencies = collect($reference)
            ->pluck('currency')
            ->merge($amountsByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        return $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => (string) ($amountsByCurrency->get($currency)['amount'] ?? '0'),
        ])->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{month: string, label: string, receivable: array<int, array{currency: string, amount: string}>}>
     */
    private function monthlyAmountGroups(Collection $rows, string $column, CarbonInterface $start): array
    {
        $map = $this->monthlyAmountMap($rows, $column);

        return collect(array_keys($map))
            ->sort()
            ->filter(fn (string $month): bool => Carbon::parse($month)->gte($start->copy()->startOfMonth()))
            ->map(fn (string $month): array => [
                'month' => $month,
                'label' => Carbon::parse($month)->format('M Y'),
                $column => $this->mapToGroups($map[$month] ?? []),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, array{id: int, name: string}>  $accessibleProperties
     * @param  Collection<int, int>  $propertyIds
     * @param  Collection<int, object>  $invoiceRows
     * @param  Collection<int, object>  $expenseRows
     * @param  Collection<int, object>  $allocationRows
     * @param  array<int, array{id: int, name: string, total_units: int, occupied_units: int, occupancy_percentage: int}>  $occupancyProperties
     * @return array<int, array<string, mixed>>
     */
    private function propertyPerformance(
        Collection $accessibleProperties,
        Collection $propertyIds,
        Collection $invoiceRows,
        Collection $expenseRows,
        Collection $allocationRows,
        array $occupancyProperties,
    ): array {
        $properties = $accessibleProperties->filter(fn (array $property): bool => $propertyIds->contains($property['id']));
        $invoiceAmounts = $this->propertyAmountMap($invoiceRows, 'revenue');
        $expenseAmounts = $this->propertyAmountMap($expenseRows, 'expenses');
        $collectedAmounts = $this->propertyAmountMap($allocationRows, 'collected');
        $outstandingAmounts = $this->propertyAmountMap($invoiceRows, 'outstanding_amount');

        $occupancyByProperty = collect($occupancyProperties)->keyBy('id');

        return $properties->map(function (array $property) use ($invoiceAmounts, $expenseAmounts, $collectedAmounts, $outstandingAmounts, $occupancyByProperty): array {
            $revenue = $this->mapToGroups($invoiceAmounts[$property['id']] ?? []);
            $expenses = $this->mapToGroups($expenseAmounts[$property['id']] ?? []);
            $noi = $this->subtractGroups($revenue, $expenses);
            $collected = $this->completeAmountGroups($this->mapToGroups($collectedAmounts[$property['id']] ?? []), $revenue);
            $outstanding = $this->mapToGroups($outstandingAmounts[$property['id']] ?? []);

            return [
                'id' => $property['id'],
                'name' => $property['name'],
                'revenue' => $revenue,
                'expenses' => $expenses,
                'noi' => $noi,
                'operating_margin' => $this->percentageGroups($noi, $revenue),
                'billed' => $revenue,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'collection_rate' => $this->percentageGroups($collected, $revenue),
                'occupancy' => $occupancyByProperty->get($property['id'], [
                    'total_units' => 0,
                    'occupied_units' => 0,
                    'occupancy_percentage' => 0,
                ]),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, string>>
     */
    private function propertyAmountMap(Collection $rows, string $amountColumn): array
    {
        $map = [];

        foreach ($rows as $row) {
            $propertyId = (int) $row->property_id;
            $currency = (string) $row->currency;
            $map[$propertyId][$currency] = isset($map[$propertyId][$currency])
                ? BigDecimal::of($map[$propertyId][$currency])->plus((string) ($row->{$amountColumn} ?? '0'))->toString()
                : BigDecimal::of((string) ($row->{$amountColumn} ?? '0'))->toString();
        }

        return $map;
    }

    /** @param array<int, array{id: int, name: string, total_units: int, occupied_units: int, occupancy_percentage: int}> $properties */
    private function occupancy(array $properties): array
    {
        $totalUnits = array_sum(array_column($properties, 'total_units'));
        $occupiedUnits = array_sum(array_column($properties, 'occupied_units'));

        return [
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'occupancy_percentage' => $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0,
            'properties' => $properties,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{category_id: int, category_label: string, amounts: array<int, array{currency: string, amount: string}>}>
     */
    private function expenseBreakdown(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (object $row): string => (string) $row->category_id)
            ->map(function (Collection $categoryRows, string $categoryId): array {
                $first = $categoryRows->first();

                return [
                    'category_id' => (int) $categoryId,
                    'category_label' => (string) $first->category_label,
                    'amounts' => $this->amountGroups($categoryRows, 'expenses'),
                ];
            })
            ->sortBy('category_label')
            ->values()
            ->all();
    }
}
