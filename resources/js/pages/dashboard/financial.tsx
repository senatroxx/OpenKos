import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    BarChart3,
    Building2,
    CalendarRange,
    ChevronDown,
    CircleDollarSign,
    Receipt,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
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
            subParams={subtext}
            value={
                <CurrencyAmountList
                    groups={groups}
                    compact
                    className={variant === 'green' ? 'text-chart-2' : undefined}
                />
            }
            valueFullWidth
            icon={Icon}
            variant={variant}
            emphasis="subtle"
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

function compactPerformanceAmount(group: MoneyAggregate): string {
    const match = group.amount.match(/^(-?)(\d+)(?:\.(\d+))?$/);

    if (!match) {
        return formatPrice(group.amount, group.currency);
    }

    const [, sign, whole, fraction = ''] = match;
    const absoluteAmount = BigInt(`${whole}${fraction}` || '0');
    const denominator = 10n ** BigInt(fraction.length);
    const units = [
        { value: 1_000_000_000n, suffix: 'B' },
        { value: 1_000_000n, suffix: 'M' },
        { value: 1_000n, suffix: 'K' },
    ];
    let unitIndex = units.findIndex(({ value }) => BigInt(whole) >= value);

    if (unitIndex === -1) {
        return formatPrice(group.amount, group.currency).replace(
            new RegExp(`^${group.currency}\\s*`),
            '',
        );
    }

    const compactValue = (value: bigint) => {
        const scaled = absoluteAmount * 10n;
        const divisor = value * denominator;
        let tenths = scaled / divisor;

        if ((scaled % divisor) * 2n >= divisor) {
            tenths += 1n;
        }

        return tenths;
    };

    let tenths = compactValue(units[unitIndex].value);

    if (tenths >= 1000n && unitIndex > 0) {
        unitIndex -= 1;
        tenths = compactValue(units[unitIndex].value);
    }

    const wholePart = tenths / 10n;
    const decimalPart = tenths % 10n;

    return `${sign}${wholePart}${decimalPart > 0n ? `.${decimalPart}` : ''}${units[unitIndex].suffix}`;
}

function PerformanceValue({ groups }: { groups: MoneyAggregate[] }) {
    if (groups.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const exactValues = groups
        .map((group) => formatPrice(group.amount, group.currency))
        .join(' · ');

    return (
        <div
            className="flex flex-col items-end gap-1 text-xs tabular-nums sm:text-sm"
            title={exactValues}
            aria-label={exactValues}
        >
            {groups.map((group) => (
                <span
                    key={group.currency}
                    className="inline-flex items-baseline gap-2 whitespace-nowrap"
                >
                    <span className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                        {group.currency}
                    </span>
                    <span className="font-semibold">
                        {compactPerformanceAmount(group)}
                    </span>
                </span>
            ))}
        </div>
    );
}

function PerformanceRate({ rates }: { rates: RateAggregate[] }) {
    if (rates.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-col items-end gap-1 text-xs tabular-nums sm:text-sm">
            {rates.map((rate) => (
                <span key={rate.currency} className="whitespace-nowrap">
                    <span className="mr-2 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                        {rate.currency}
                    </span>
                    <span className="font-semibold">{rate.rate}%</span>
                </span>
            ))}
        </div>
    );
}

function CompactPerformanceAmounts({ groups }: { groups: MoneyAggregate[] }) {
    if (groups.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const exactValues = groups
        .map((group) => formatPrice(group.amount, group.currency))
        .join(' · ');

    return (
        <div
            className="flex flex-col gap-1 text-sm font-semibold tabular-nums"
            title={exactValues}
            aria-label={exactValues}
        >
            {groups.map((group) => (
                <span key={group.currency} className="whitespace-nowrap">
                    {group.currency} {compactPerformanceAmount(group)}
                </span>
            ))}
        </div>
    );
}

function CompactPerformanceRates({ rates }: { rates: RateAggregate[] }) {
    if (rates.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-x-3 gap-y-1 text-sm font-semibold tabular-nums">
            {rates.map((rate) => (
                <span key={rate.currency} className="whitespace-nowrap">
                    {rate.currency} {rate.rate}%
                </span>
            ))}
        </div>
    );
}

function PerformanceDetailBlock({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="mt-1.5">{children}</div>
        </div>
    );
}

function PropertyPerformanceDetails({
    row,
}: {
    row: FinancialPropertyPerformance;
}) {
    return (
        <div className="bg-muted/30 p-4">
            <div className="space-y-5">
                <section>
                    <h4 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {t('Financial')}
                    </h4>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <PerformanceDetailBlock label={t('Revenue')}>
                            <CompactPerformanceAmounts groups={row.revenue} />
                        </PerformanceDetailBlock>
                        <PerformanceDetailBlock label={t('Expenses')}>
                            <CompactPerformanceAmounts groups={row.expenses} />
                        </PerformanceDetailBlock>
                        <PerformanceDetailBlock label={t('NOI')}>
                            <CompactPerformanceAmounts groups={row.noi} />
                        </PerformanceDetailBlock>
                    </div>
                    <div className="mt-4 border-t border-border/60 pt-3">
                        <PerformanceDetailBlock label={t('Operating margin')}>
                            <CompactPerformanceRates
                                rates={row.operating_margin}
                            />
                        </PerformanceDetailBlock>
                    </div>
                </section>

                <section className="border-t border-border/60 pt-5">
                    <h4 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {t('Collections')}
                    </h4>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <PerformanceDetailBlock label={t('Billed')}>
                            <CompactPerformanceAmounts groups={row.billed} />
                        </PerformanceDetailBlock>
                        <PerformanceDetailBlock label={t('Collected')}>
                            <CompactPerformanceAmounts groups={row.collected} />
                        </PerformanceDetailBlock>
                        <PerformanceDetailBlock label={t('Outstanding')}>
                            <CompactPerformanceAmounts
                                groups={row.outstanding}
                            />
                        </PerformanceDetailBlock>
                    </div>
                    <div className="mt-4 border-t border-border/60 pt-3">
                        <PerformanceDetailBlock label={t('Collection rate')}>
                            <CompactPerformanceRates
                                rates={row.collection_rate}
                            />
                        </PerformanceDetailBlock>
                    </div>
                </section>

                <section className="border-t border-border/60 pt-5">
                    <h4 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {t('Occupancy')}
                    </h4>
                    <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <span className="text-sm font-semibold tabular-nums">
                            {row.occupancy.occupied_units} /{' '}
                            {row.occupancy.total_units} {t('units')}
                        </span>
                        <span className="text-sm font-medium text-muted-foreground tabular-nums">
                            {row.occupancy.occupancy_percentage}%{' '}
                            {t('occupied')}
                        </span>
                    </div>
                </section>
            </div>
        </div>
    );
}

function PerformanceTable({ rows }: { rows: FinancialPropertyPerformance[] }) {
    const [expandedPropertyId, setExpandedPropertyId] = useState<number | null>(
        null,
    );

    if (rows.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No accessible properties.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <th className="px-2 py-3 font-medium">
                            {t('Property')}
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            {t('Revenue')}
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            {t('Expenses')}
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            {t('NOI')}
                        </th>
                        <th className="px-2 py-3 text-right font-medium">
                            {t('Margin')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const isExpanded = expandedPropertyId === row.id;
                        const detailId = `property-performance-details-${row.id}`;

                        return (
                            <Fragment key={row.id}>
                                <tr
                                    className={`border-b border-border/60 hover:bg-muted/30 ${isExpanded ? 'bg-muted/20' : ''}`}
                                >
                                    <td className="px-2 py-3">
                                        <button
                                            type="button"
                                            className="group flex w-full min-w-0 items-center gap-2 rounded-md py-1 text-left font-medium transition-colors outline-none hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            aria-expanded={isExpanded}
                                            aria-controls={detailId}
                                            onClick={() =>
                                                setExpandedPropertyId(
                                                    isExpanded ? null : row.id,
                                                )
                                            }
                                        >
                                            <span className="truncate">
                                                {row.name}
                                            </span>
                                            <ChevronDown
                                                className={`size-4 shrink-0 text-muted-foreground transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                                                aria-hidden="true"
                                            />
                                            <span className="sr-only">
                                                {isExpanded
                                                    ? t('Hide details')
                                                    : t('View details')}
                                            </span>
                                        </button>
                                    </td>
                                    <td className="px-2 py-3 text-right">
                                        <PerformanceValue
                                            groups={row.revenue}
                                        />
                                    </td>
                                    <td className="px-2 py-3 text-right">
                                        <PerformanceValue
                                            groups={row.expenses}
                                        />
                                    </td>
                                    <td className="px-2 py-3 text-right">
                                        <PerformanceValue groups={row.noi} />
                                    </td>
                                    <td className="px-2 py-3 text-right">
                                        <PerformanceRate
                                            rates={row.operating_margin}
                                        />
                                    </td>
                                </tr>
                                {isExpanded && (
                                    <tr className="border-b border-border/60">
                                        <td
                                            id={detailId}
                                            colSpan={5}
                                            className="px-2 pb-4"
                                        >
                                            <PropertyPerformanceDetails
                                                row={row}
                                            />
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        );
                    })}
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
                                <div className="text-chart-2">
                                    <RateList
                                        rates={
                                            financial.collections
                                                .collection_rate
                                        }
                                    />
                                </div>
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
                                                className: 'text-chart-2',
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
                                                className: 'text-chart-2',
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
                                    'Selected-period financial performance by accessible property. Select a property to view full detail.',
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
