import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    BarChart3,
    Building2,
    CalendarRange,
    CircleDollarSign,
    Receipt,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType } from 'react';
import { CurrencyAmountList } from '@/components/features/dashboard/currency-amount-list';
import {
    ExpenseBreakdownChart,
    FinancialTrendChart,
    subtractCurrencyAmounts,
    sumCurrencyAmounts,
} from '@/components/features/dashboard/financial-trend-chart';
import { MetricCard } from '@/components/shared/metric-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { financial as dashboardFinancial } from '@/routes/dashboard';
import type {
    FinancialDashboardData,
    FinancialPropertyPerformance,
    RateAggregate,
    MoneyAggregate,
} from '@/types';

type Period = 'current_month' | 'ytd';

type PageProps = {
    financial: FinancialDashboardData;
    filters: {
        period: Period;
        property_id: number | null;
    };
    properties: Array<{ id: number; name: string }>;
};

type FinancialSeriesKey = 'revenue' | 'expenses' | 'noi' | 'collected';

function currenciesFromPoints(
    points: Array<Partial<Record<FinancialSeriesKey, MoneyAggregate[]>>>,
    keys: FinancialSeriesKey[],
): string[] {
    const groups = points.flatMap((point) =>
        keys.flatMap((key) => point[key] ?? []),
    );

    return [...new Set(groups.map((group) => group.currency))].sort();
}

function RateList({ rates }: { rates: RateAggregate[] }) {
    if (rates.length === 0) {
        return (
            <span className="text-2xl font-bold text-muted-foreground">—</span>
        );
    }

    return (
        <div className="space-y-2">
            {rates.map((rate) => {
                const percentage = Number.parseFloat(rate.rate);

                return (
                    <div key={rate.currency} className="space-y-1">
                        <div className="flex items-center justify-between text-sm font-semibold tabular-nums">
                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                {rate.currency}
                            </span>
                            <span>{rate.rate}%</span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${Math.min(100, Math.max(0, percentage))}%`,
                                }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function FinancialCard({
    label,
    groups,
    icon: Icon,
    variant,
    subtext,
}: {
    label: string;
    groups: MoneyAggregate[];
    icon: ComponentType<{ className?: string }>;
    variant: 'blue' | 'green' | 'red' | 'amber';
    subtext: string;
}) {
    return (
        <MetricCard
            label={label}
            value={
                <span className="text-sm font-medium text-muted-foreground">
                    {subtext}
                </span>
            }
            subtext={<CurrencyAmountList groups={groups} compact />}
            icon={Icon}
            variant={variant}
            emphasis="subtle"
            subtextFullWidth
        />
    );
}

function ChartCurrencySelector({
    currencies,
    value,
    onValueChange,
}: {
    currencies: string[];
    value: string;
    onValueChange: (value: string) => void;
}) {
    if (currencies.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            <span className="text-xs font-medium text-muted-foreground">
                {t('Currency')}
            </span>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger
                    className="w-[104px] bg-card"
                    aria-label={t('Chart currency')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {currencies.map((currency) => (
                        <SelectItem key={currency} value={currency}>
                            {currency}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function ChartSummary({
    currency,
    metrics,
}: {
    currency: string;
    metrics: Array<{
        label: string;
        amount: string;
        className: string;
    }>;
}) {
    return (
        <div className="space-y-3 border-y border-border/60 py-3">
            <p className="text-xs font-medium text-muted-foreground">
                {t('Selected period')} · {currency}
            </p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
                {metrics.map((metric) => (
                    <div key={metric.label} className="min-w-0">
                        <p className="text-xs font-medium text-muted-foreground">
                            {metric.label}
                        </p>
                        <p
                            className={`mt-1 overflow-hidden text-sm font-semibold whitespace-nowrap tabular-nums sm:text-base ${metric.className}`}
                        >
                            {formatPrice(metric.amount, currency)}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function amountForCurrency(groups: MoneyAggregate[], currency: string): string {
    return groups.find((group) => group.currency === currency)?.amount ?? '0';
}

function PerformanceValue({ groups }: { groups: MoneyAggregate[] }) {
    return <CurrencyAmountList groups={groups} compact />;
}

function PerformanceTable({ rows }: { rows: FinancialPropertyPerformance[] }) {
    if (rows.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No accessible properties.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <th className="px-2 py-3 font-medium">Property</th>
                        <th className="px-2 py-3 text-right font-medium">
                            Revenue
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Expenses
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            NOI
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Margin
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Applied
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Outstanding
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Rate
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            Occupancy
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className="border-b border-border/60 last:border-0"
                        >
                            <td className="px-2 py-3 font-medium">
                                {row.name}
                            </td>
                            <td className="px-2 py-3 text-right">
                                <PerformanceValue groups={row.revenue} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <PerformanceValue groups={row.expenses} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <PerformanceValue groups={row.noi} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <RateList rates={row.operating_margin} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <PerformanceValue groups={row.collected} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <PerformanceValue groups={row.outstanding} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                <RateList rates={row.collection_rate} />
                            </td>
                            <td className="px-2 py-3 text-right">
                                {row.occupancy.occupancy_percentage}% ·{' '}
                                {row.occupancy.occupied_units}/
                                {row.occupancy.total_units}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Financial({
    financial,
    filters,
    properties,
}: PageProps) {
    const trendCurrencies = currenciesFromPoints(financial.trends, [
        'revenue',
        'expenses',
        'noi',
    ]);
    const cashFlowCurrencies = currenciesFromPoints(financial.cash_flow, [
        'collected',
        'expenses',
    ]);
    const expenseCurrencies = [
        ...new Set(
            financial.expense_breakdown.flatMap((category) =>
                category.amounts.map((group) => group.currency),
            ),
        ),
    ].sort();
    const [trendCurrency, setTrendCurrency] = useState(
        () => trendCurrencies[0] ?? '',
    );
    const [cashFlowCurrency, setCashFlowCurrency] = useState(
        () => cashFlowCurrencies[0] ?? '',
    );
    const [expenseCurrency, setExpenseCurrency] = useState(
        () => expenseCurrencies[0] ?? '',
    );
    const selectedTrendCurrency = trendCurrencies.includes(trendCurrency)
        ? trendCurrency
        : (trendCurrencies[0] ?? '');
    const selectedCashFlowCurrency = cashFlowCurrencies.includes(
        cashFlowCurrency,
    )
        ? cashFlowCurrency
        : (cashFlowCurrencies[0] ?? '');
    const selectedExpenseCurrency = expenseCurrencies.includes(expenseCurrency)
        ? expenseCurrency
        : (expenseCurrencies[0] ?? '');
    const cashFlowCollected = sumCurrencyAmounts(
        financial.cash_flow.flatMap((point) => point.collected),
        selectedCashFlowCurrency,
    );
    const cashFlowExpenses = sumCurrencyAmounts(
        financial.cash_flow.flatMap((point) => point.expenses),
        selectedCashFlowCurrency,
    );
    const netCashFlow = subtractCurrencyAmounts(
        cashFlowCollected,
        cashFlowExpenses,
    );

    function updateFilters(changes: {
        period?: Period;
        propertyId?: number | null;
    }) {
        const period = changes.period ?? filters.period;
        const propertyId =
            changes.propertyId === undefined
                ? filters.property_id
                : changes.propertyId;

        router.get(
            dashboardFinancial({
                query: {
                    period,
                    property_id: propertyId ?? undefined,
                },
            }).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title={t('Financial Dashboard')} />
            <div className="flex h-full flex-1 flex-col overflow-x-auto p-4 md:p-6 lg:p-8">
                <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            {t('Financial Dashboard')}
                        </h1>
                        <p className="mt-1 max-w-2xl text-xs text-muted-foreground sm:text-sm">
                            {t(
                                'Understand billed performance, profitability, and cash movement across your properties.',
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Select
                            value={filters.period}
                            onValueChange={(value) =>
                                updateFilters({ period: value as Period })
                            }
                        >
                            <SelectTrigger
                                className="w-[150px] bg-card"
                                aria-label={t('Reporting period')}
                            >
                                <CalendarRange className="size-4 text-muted-foreground" />
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="current_month">
                                    {t('Current Month')}
                                </SelectItem>
                                <SelectItem value="ytd">
                                    {t('Year to Date')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={
                                filters.property_id === null
                                    ? 'all'
                                    : String(filters.property_id)
                            }
                            onValueChange={(value) =>
                                updateFilters({
                                    propertyId:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                className="w-[190px] bg-card"
                                aria-label={t('Property')}
                            >
                                <Building2 className="size-4 text-muted-foreground" />
                                <SelectValue
                                    placeholder={t('All Properties')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('All Properties')}
                                </SelectItem>
                                {properties.map((property) => (
                                    <SelectItem
                                        key={property.id}
                                        value={String(property.id)}
                                    >
                                        {property.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="mb-8 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-100">
                    <CircleDollarSign className="size-4 shrink-0" />
                    <p>
                        {t(
                            'Revenue and NOI use billed accruals. Cash Flow uses confirmed payments by payment date.',
                        )}
                    </p>
                </div>

                <section className="mb-8 flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('Financial Overview')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <FinancialCard
                            label={t('Billed Revenue')}
                            groups={financial.overview.revenue}
                            icon={TrendingUp}
                            variant="green"
                            subtext={t('Accrual basis')}
                        />
                        <FinancialCard
                            label={t('Operating Expenses')}
                            groups={financial.overview.expenses}
                            icon={TrendingDown}
                            variant="amber"
                            subtext={t('Active expenses only')}
                        />
                        <FinancialCard
                            label={t('NOI')}
                            groups={financial.overview.noi}
                            icon={BarChart3}
                            variant="blue"
                            subtext={t('Revenue minus expenses')}
                        />
                        <MetricCard
                            label={t('Operating Margin')}
                            value={
                                <RateList
                                    rates={financial.overview.operating_margin}
                                />
                            }
                            subtext={t('Per-currency percentage')}
                            icon={CircleDollarSign}
                            variant="blue"
                            emphasis="subtle"
                            subtextFullWidth
                        />
                    </div>
                </section>

                <section className="mb-8 flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('Collections')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <FinancialCard
                            label={t('Billed')}
                            groups={financial.collections.billed}
                            icon={Receipt}
                            variant="blue"
                            subtext={t('Selected invoice cohort')}
                        />
                        <FinancialCard
                            label={t('Applied Collections')}
                            groups={financial.collections.collected}
                            icon={Banknote}
                            variant="green"
                            subtext={t(
                                'Confirmed allocations to selected invoices; any payment date',
                            )}
                        />
                        <FinancialCard
                            label={t('Outstanding Receivables')}
                            groups={financial.collections.outstanding}
                            icon={CircleDollarSign}
                            variant="red"
                            subtext={t(
                                'Selected billed invoices minus confirmed payments',
                            )}
                        />
                        <MetricCard
                            label={t('Collection Rate')}
                            value={
                                <RateList
                                    rates={
                                        financial.collections.collection_rate
                                    }
                                />
                            }
                            subtext={t(
                                'Confirmed applied ÷ selected billed invoices',
                            )}
                            icon={BarChart3}
                            variant="green"
                            emphasis="subtle"
                            subtextFullWidth
                        />
                    </div>
                </section>

                <section className="mb-8 grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-1.5">
                                    <CardTitle>
                                        {t('Revenue, Expenses & NOI')}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Last 12 calendar-month buckets, independent of the selected period',
                                        )}
                                    </p>
                                </div>
                                <ChartCurrencySelector
                                    currencies={trendCurrencies}
                                    value={selectedTrendCurrency}
                                    onValueChange={setTrendCurrency}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedTrendCurrency ? (
                                <>
                                    <ChartSummary
                                        currency={selectedTrendCurrency}
                                        metrics={[
                                            {
                                                label: t('Revenue'),
                                                amount: amountForCurrency(
                                                    financial.overview.revenue,
                                                    selectedTrendCurrency,
                                                ),
                                                className:
                                                    'text-surface-green-foreground',
                                            },
                                            {
                                                label: t('Expenses'),
                                                amount: amountForCurrency(
                                                    financial.overview.expenses,
                                                    selectedTrendCurrency,
                                                ),
                                                className:
                                                    'text-surface-amber-foreground',
                                            },
                                            {
                                                label: t('NOI'),
                                                amount: amountForCurrency(
                                                    financial.overview.noi,
                                                    selectedTrendCurrency,
                                                ),
                                                className:
                                                    'text-surface-blue-foreground',
                                            },
                                        ]}
                                    />
                                    <FinancialTrendChart
                                        points={financial.trends}
                                        currency={selectedTrendCurrency}
                                        series={[
                                            {
                                                key: 'revenue',
                                                label: t('Revenue'),
                                            },
                                            {
                                                key: 'expenses',
                                                label: t('Expenses'),
                                            },
                                            {
                                                key: 'noi',
                                                label: t('NOI'),
                                            },
                                        ]}
                                    />
                                </>
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No financial activity recorded.')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-1.5">
                                    <CardTitle>{t('Cash Flow')}</CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Selected-period cash movement by payment and expense date',
                                        )}
                                    </p>
                                </div>
                                <ChartCurrencySelector
                                    currencies={cashFlowCurrencies}
                                    value={selectedCashFlowCurrency}
                                    onValueChange={setCashFlowCurrency}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {selectedCashFlowCurrency ? (
                                <>
                                    <ChartSummary
                                        currency={selectedCashFlowCurrency}
                                        metrics={[
                                            {
                                                label: t('Cash Collected'),
                                                amount: cashFlowCollected,
                                                className:
                                                    'text-surface-green-foreground',
                                            },
                                            {
                                                label: t('Expenses'),
                                                amount: cashFlowExpenses,
                                                className:
                                                    'text-surface-amber-foreground',
                                            },
                                            {
                                                label: t('Net Cash Flow'),
                                                amount: netCashFlow,
                                                className:
                                                    'text-surface-blue-foreground',
                                            },
                                        ]}
                                    />
                                    <FinancialTrendChart
                                        points={financial.cash_flow}
                                        currency={selectedCashFlowCurrency}
                                        series={[
                                            {
                                                key: 'collected',
                                                label: t('Cash Collected'),
                                            },
                                            {
                                                key: 'expenses',
                                                label: t('Expenses'),
                                            },
                                        ]}
                                    />
                                </>
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No cash movement recorded.')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="mb-8 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Property Performance')}</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Selected-period financial performance by accessible property',
                                )}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <PerformanceTable
                                rows={financial.property_performance}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Current Occupancy')}</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                {t('Current unit and active lease state only')}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-6 flex items-end justify-between">
                                <div>
                                    <p className="text-3xl font-bold tabular-nums">
                                        {
                                            financial.occupancy
                                                .occupancy_percentage
                                        }
                                        %
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {financial.occupancy.occupied_units} /{' '}
                                        {financial.occupancy.total_units}{' '}
                                        {t('units occupied')}
                                    </p>
                                </div>
                                <Building2 className="size-8 text-muted-foreground/60" />
                            </div>
                            <div className="space-y-3">
                                {financial.occupancy.properties.map(
                                    (property) => (
                                        <div
                                            key={property.id}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span className="truncate font-medium">
                                                {property.name}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                                {property.occupancy_percentage}%
                                                · {property.occupied_units}/
                                                {property.total_units}
                                            </span>
                                        </div>
                                    ),
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="mb-8 grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-1.5">
                                    <CardTitle>
                                        {t('Expense Breakdown')}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Active operating expenses by category for the selected period',
                                        )}
                                    </p>
                                </div>
                                <ChartCurrencySelector
                                    currencies={expenseCurrencies}
                                    value={selectedExpenseCurrency}
                                    onValueChange={setExpenseCurrency}
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            {selectedExpenseCurrency ? (
                                <ExpenseBreakdownChart
                                    categories={financial.expense_breakdown}
                                    currency={selectedExpenseCurrency}
                                />
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No operating expenses recorded.')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Upcoming Receivables')}</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Forecast summary from generated invoices; no collection workflow',
                                )}
                            </p>
                        </CardHeader>
                        <CardContent>
                            {financial.upcoming_receivables.length > 0 ? (
                                <div className="space-y-4">
                                    {financial.upcoming_receivables.map(
                                        (bucket) => (
                                            <div
                                                key={bucket.month}
                                                className="flex items-center justify-between gap-4 border-b border-border/60 pb-3 last:border-0 last:pb-0"
                                            >
                                                <span className="font-medium">
                                                    {bucket.label}
                                                </span>
                                                <CurrencyAmountList
                                                    groups={bucket.receivable}
                                                    compact
                                                />
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('No upcoming receivables recorded.')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <p className="mt-auto text-xs text-muted-foreground">
                    {t('Reporting through')} {formatDate(financial.period_end)}{' '}
                    ·{' '}
                    {t(
                        'Trends remain independent of the selected summary period.',
                    )}
                </p>
            </div>
        </>
    );
}

Financial.layout = {
    breadcrumbs: [
        {
            title: 'Financial Dashboard',
            href: dashboardFinancial(),
        },
    ],
};
