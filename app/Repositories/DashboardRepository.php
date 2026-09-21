<?php

namespace App\Repositories;

use App\Data\Dashboard\FinancialDashboardData;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DashboardRepository
{
    /**
     * @param  Collection<int, int>  $propertyIds
     */
    public function overviewFinance(Collection $propertyIds, CarbonInterface $now): array
    {
        $periodStart = $now->copy()->startOfMonth();
        $periodEnd = $now->copy()->endOfMonth();
        $leaseIds = Lease::query()->whereIn('property_id', $propertyIds)->select('id');

        return [
            'active_leases' => Lease::query()
                ->whereIn('property_id', $propertyIds)
                ->active()
                ->get(['rent_amount', 'currency'])
                ->map(fn (Lease $lease): object => (object) [
                    'rent_amount' => (string) $lease->rent_amount,
                    'currency' => $lease->currency,
                ]),
            'payments' => Payment::query()
                ->where('status', PaymentStatus::Confirmed->value)
                ->whereHas('invoice', fn (Builder $query) => $query
                    ->whereBetween('period_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->whereIn('lease_id', $leaseIds))
                ->get(['amount', 'currency'])
                ->map(fn (Payment $payment): object => (object) [
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                ]),
            'invoices' => Invoice::query()
                ->whereIn('lease_id', $leaseIds)
                ->whereBetween('period_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
                ->get(['total', 'amount_paid', 'currency'])
                ->map(fn (Invoice $invoice): object => (object) [
                    'total' => (string) $invoice->total,
                    'amount_paid' => (string) $invoice->amount_paid,
                    'currency' => $invoice->currency,
                ]),
            'expenses' => Expense::query()
                ->active()
                ->whereIn('property_id', $propertyIds)
                ->whereBetween('expense_date', [
                    $periodStart->copy()->subMonthNoOverflow()->toDateString(),
                    $periodEnd->toDateString(),
                ])
                ->get(['amount', 'currency', 'expense_date'])
                ->map(fn (Expense $expense): object => (object) [
                    'amount' => (string) $expense->amount,
                    'currency' => $expense->currency,
                    'expense_date' => $expense->expense_date->toDateString(),
                ]),
        ];
    }

    /**
     * @param  Collection<int, int>  $propertyIds
     */
    public function financialData(
        Collection $propertyIds,
        CarbonInterface $trendStart,
        CarbonInterface $now,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): FinancialDashboardData {
        return new FinancialDashboardData(
            invoiceRows: $this->invoiceAggregates($propertyIds, $trendStart, $now),
            expenseRows: $this->expenseAggregates($propertyIds, $trendStart, $now),
            paymentRows: $this->paymentAggregates($propertyIds, $trendStart, $now),
            allocationRows: $this->allocationAggregates($propertyIds, $periodStart, $periodEnd),
            upcomingRows: $this->upcomingReceivableAggregates($propertyIds, $now),
            occupancyProperties: $this->occupancy($propertyIds),
        );
    }

    /** @return Collection<int, object> */
    private function invoiceAggregates(Collection $propertyIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $month = $this->monthExpression('invoices.period_start');

        return Invoice::query()
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->whereIn('leases.property_id', $propertyIds)
            ->whereBetween('invoices.period_start', [$start->toDateString(), $end->toDateString()])
            ->whereIn('invoices.status', $this->eligibleInvoiceStatuses())
            ->selectRaw("leases.property_id, invoices.currency, {$month} as month, SUM(invoices.total) as revenue, SUM(invoices.total - invoices.amount_paid) as outstanding_amount")
            ->groupBy('leases.property_id', 'invoices.currency')
            ->groupByRaw($month)
            ->get();
    }

    /** @return Collection<int, object> */
    private function expenseAggregates(Collection $propertyIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $month = $this->monthExpression('expenses.expense_date');

        return Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereIn('expenses.property_id', $propertyIds)
            ->where('expenses.status', ExpenseStatus::Active->value)
            ->whereBetween('expenses.expense_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("expenses.property_id, expenses.expense_category_id as category_id, expense_categories.label as category_label, expenses.currency, {$month} as month, SUM(expenses.amount) as expenses")
            ->groupBy('expenses.property_id', 'expenses.expense_category_id', 'expense_categories.label', 'expenses.currency')
            ->groupByRaw($month)
            ->get();
    }

    /** @return Collection<int, object> */
    private function paymentAggregates(Collection $propertyIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $month = $this->monthExpression('payments.payment_date');
        $currency = 'COALESCE(payments.currency, invoices.currency)';

        return Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->whereIn('leases.property_id', $propertyIds)
            ->where('payments.status', PaymentStatus::Confirmed->value)
            ->whereBetween('payments.payment_date', [$start, $end])
            ->selectRaw("leases.property_id, {$currency} as currency, {$month} as month, SUM(payments.amount) as collected")
            ->groupBy('leases.property_id')
            ->groupByRaw($currency)
            ->groupByRaw($month)
            ->get();
    }

    /** @return Collection<int, object> */
    private function allocationAggregates(Collection $propertyIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->whereIn('leases.property_id', $propertyIds)
            ->where('payments.status', PaymentStatus::Confirmed->value)
            ->whereIn('invoices.status', $this->eligibleInvoiceStatuses())
            ->whereBetween('invoices.period_start', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('leases.property_id, invoices.currency, SUM(payment_allocations.amount) as collected')
            ->groupBy('leases.property_id', 'invoices.currency')
            ->get();
    }

    /** @return Collection<int, object> */
    private function upcomingReceivableAggregates(Collection $propertyIds, CarbonInterface $now): Collection
    {
        $month = $this->monthExpression('invoices.due_date');

        return Invoice::query()
            ->join('leases', 'leases.id', '=', 'invoices.lease_id')
            ->whereIn('leases.property_id', $propertyIds)
            ->whereIn('invoices.status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->whereDate('invoices.due_date', '>=', $now->toDateString())
            ->selectRaw("leases.property_id, invoices.currency, {$month} as month, SUM(invoices.total - invoices.amount_paid) as receivable")
            ->groupBy('leases.property_id', 'invoices.currency')
            ->groupByRaw($month)
            ->orderByRaw($month)
            ->get();
    }

    /** @return array<int, array{id: int, name: string, total_units: int, occupied_units: int, occupancy_percentage: int}> */
    private function occupancy(Collection $propertyIds): array
    {
        return Property::query()
            ->whereIn('id', $propertyIds)
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $query) => $query
                    ->where(function (Builder $query): void {
                        $query->where('status', UnitStatus::Occupied)
                            ->orWhereHas('leases', fn (Builder $query) => $query->active())
                            ->orWhereHas('property.activeWholePropertyLeases');
                    }),
            ])
            ->get(['id', 'name'])
            ->map(fn (Property $property): array => [
                'id' => $property->id,
                'name' => $property->name,
                'total_units' => (int) $property->units_count,
                'occupied_units' => (int) $property->occupied_units_count,
                'occupancy_percentage' => $property->units_count > 0
                    ? (int) round(($property->occupied_units_count / $property->units_count) * 100)
                    : 0,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function eligibleInvoiceStatuses(): array
    {
        return [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value, InvoiceStatus::Paid->value];
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-01', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m-01')",
            'pgsql' => "DATE_TRUNC('month', {$column})::date",
            'sqlsrv' => "DATEFROMPARTS(YEAR({$column}), MONTH({$column}), 1)",
            default => throw new \RuntimeException('Unsupported database driver for financial month grouping.'),
        };
    }
}
