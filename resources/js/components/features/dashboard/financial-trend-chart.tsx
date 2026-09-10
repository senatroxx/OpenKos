import {
    Bar,
    BarChart,
    CartesianGrid,
    ComposedChart,
    Line,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { MoneyAggregate } from '@/types';

type SeriesKey = 'revenue' | 'expenses' | 'noi' | 'collected';

type FinancialChartPoint = {
    month: string;
    label: string;
    revenue?: MoneyAggregate[];
    expenses?: MoneyAggregate[];
    noi?: MoneyAggregate[];
    collected?: MoneyAggregate[];
};

type ChartSeries = {
    key: SeriesKey;
    label: string;
};

type FinancialTrendChartProps = {
    points: FinancialChartPoint[];
    currency: string;
    series: ChartSeries[];
};

type ExpenseBreakdownCategory = {
    category_id: number;
    category_label: string;
    amounts: MoneyAggregate[];
};

type PlotPoint = {
    month: string;
    label: string;
    raw: Partial<Record<SeriesKey, string>>;
    revenue: number;
    expenses: number;
    noi: number;
    collected: number;
};

const MONEY_SCALE = 3;
const PLOT_SCALE = 1_000_000;
const SERIES_COLORS: Record<SeriesKey, string> = {
    revenue: 'var(--surface-green-foreground)',
    expenses: 'var(--surface-amber-foreground)',
    noi: 'var(--surface-blue-foreground)',
    collected: 'var(--surface-green-foreground)',
};

const TOOLTIP_CONTENT_STYLE = {
    border: '1px solid var(--border)',
    borderRadius: '0.5rem',
    backgroundColor: 'var(--popover)',
    color: 'var(--popover-foreground)',
    boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
};

const TOOLTIP_ITEM_STYLE = {
    color: 'var(--popover-foreground)',
    padding: '2px 0',
};

function toMinorUnits(amount: string): bigint {
    const negative = amount.startsWith('-');
    const normalized =
        negative || amount.startsWith('+') ? amount.slice(1) : amount;
    const [whole, fraction = ''] = normalized.split('.');
    const scaledFraction = fraction
        .padEnd(MONEY_SCALE, '0')
        .slice(0, MONEY_SCALE);
    const value = BigInt(`${whole || '0'}${scaledFraction}`);

    return negative ? -value : value;
}

function fromMinorUnits(amount: bigint): string {
    if (amount === 0n) {
        return '0';
    }

    const negative = amount < 0n;
    const absolute = negative ? -amount : amount;
    const factor = 10n ** BigInt(MONEY_SCALE);
    const whole = absolute / factor;
    const fraction = (absolute % factor).toString().padStart(MONEY_SCALE, '0');

    return `${negative ? '-' : ''}${whole}.${fraction}`;
}

export function sumCurrencyAmounts(
    groups: MoneyAggregate[],
    currency: string,
): string {
    return fromMinorUnits(
        groups.reduce(
            (total, group) =>
                group.currency === currency
                    ? total + toMinorUnits(group.amount)
                    : total,
            0n,
        ),
    );
}

export function subtractCurrencyAmounts(left: string, right: string): string {
    return fromMinorUnits(toMinorUnits(left) - toMinorUnits(right));
}

function amountFor(
    point: FinancialChartPoint,
    key: SeriesKey,
    currency: string,
): string {
    return (
        point[key]?.find((group) => group.currency === currency)?.amount ?? '0'
    );
}

function amountForCategory(
    category: ExpenseBreakdownCategory,
    currency: string,
): string {
    return (
        category.amounts.find((group) => group.currency === currency)?.amount ??
        '0'
    );
}

function toPlotValue(amount: string, maximum: bigint): number {
    if (maximum === 0n) {
        return 0;
    }

    return Number((toMinorUnits(amount) * BigInt(PLOT_SCALE)) / maximum);
}

function seriesLabel(series: ChartSeries[], key: string): string {
    return series.find((item) => item.key === key)?.label ?? key;
}

function tooltipFormatter(
    _value: unknown,
    name: unknown,
    item: { dataKey?: unknown; payload?: unknown },
    currency: string,
    series: ChartSeries[],
): [string, string] {
    const key =
        typeof item.dataKey === 'string' ? (item.dataKey as SeriesKey) : null;
    const payload = item.payload as PlotPoint | undefined;
    const amount = key ? payload?.raw[key] : undefined;

    return [
        formatPrice(amount ?? '0', currency),
        seriesLabel(series, key ?? String(name ?? '')),
    ];
}

function ChartLegend({ series }: { series: ChartSeries[] }) {
    return (
        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {series.map((item) => (
                <span
                    key={item.key}
                    className="inline-flex items-center gap-1.5"
                >
                    <span
                        className="size-2 rounded-full"
                        style={{ backgroundColor: SERIES_COLORS[item.key] }}
                        aria-hidden="true"
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

function ChartEmptyState({ message }: { message: string }) {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            {message}
        </p>
    );
}

function chartDomain(hasNegative: boolean): [number, number] {
    return hasNegative ? [-PLOT_SCALE, PLOT_SCALE] : [0, PLOT_SCALE];
}

function monthTick(label: string): string {
    return label.split(' ')[0] ?? label;
}

export function FinancialTrendChart({
    points,
    currency,
    series,
}: FinancialTrendChartProps) {
    const maximum = points.reduce((max, point) => {
        return series.reduce((seriesMax, item) => {
            const value = toMinorUnits(amountFor(point, item.key, currency));
            const absoluteValue = value < 0n ? -value : value;

            return absoluteValue > seriesMax ? absoluteValue : seriesMax;
        }, max);
    }, 0n);

    if (points.length === 0 || series.length === 0 || maximum === 0n) {
        return (
            <ChartEmptyState message={t('No financial activity recorded.')} />
        );
    }

    const data = points.map<PlotPoint>((point) => {
        const raw: Partial<Record<SeriesKey, string>> = {};
        const values = {
            revenue: 0,
            expenses: 0,
            noi: 0,
            collected: 0,
        };

        series.forEach((item) => {
            const amount = amountFor(point, item.key, currency);

            raw[item.key] = amount;
            values[item.key] = toPlotValue(amount, maximum);
        });

        return { month: point.month, label: point.label, raw, ...values };
    });
    const hasNegative = data.some((point) =>
        series.some((item) => point[item.key] < 0),
    );

    return (
        <div
            className="space-y-3"
            role="img"
            aria-label={`${currency} financial trend chart`}
        >
            <ChartLegend series={series} />
            <div className="h-64 w-full min-w-0">
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart
                        data={data}
                        margin={{ top: 8, right: 4, left: 0, bottom: 0 }}
                    >
                        <CartesianGrid
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="label"
                            axisLine={false}
                            tickLine={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                            }}
                            interval={0}
                            tickFormatter={monthTick}
                            tickMargin={8}
                        />
                        <YAxis domain={chartDomain(hasNegative)} hide />
                        <Tooltip
                            contentStyle={TOOLTIP_CONTENT_STYLE}
                            itemStyle={TOOLTIP_ITEM_STYLE}
                            labelStyle={{
                                color: 'var(--popover-foreground)',
                                fontWeight: 600,
                                marginBottom: 4,
                            }}
                            formatter={(value, name, item) =>
                                tooltipFormatter(
                                    value,
                                    name,
                                    item,
                                    currency,
                                    series,
                                )
                            }
                            cursor={{
                                fill: 'var(--muted)',
                                opacity: 0.35,
                            }}
                        />
                        {hasNegative && (
                            <ReferenceLine
                                y={0}
                                stroke="var(--border)"
                                strokeWidth={1}
                            />
                        )}
                        {series.map((item) =>
                            item.key === 'noi' ? (
                                <Line
                                    key={item.key}
                                    type="monotone"
                                    dataKey={item.key}
                                    name={item.label}
                                    stroke={SERIES_COLORS[item.key]}
                                    strokeWidth={2}
                                    dot={{
                                        r: 3,
                                        fill: SERIES_COLORS[item.key],
                                        strokeWidth: 0,
                                    }}
                                    activeDot={{ r: 5 }}
                                    isAnimationActive={false}
                                />
                            ) : (
                                <Bar
                                    key={item.key}
                                    dataKey={item.key}
                                    name={item.label}
                                    fill={SERIES_COLORS[item.key]}
                                    radius={[3, 3, 0, 0]}
                                    maxBarSize={18}
                                    isAnimationActive={false}
                                />
                            ),
                        )}
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function ExpenseBreakdownChart({
    categories,
    currency,
}: {
    categories: ExpenseBreakdownCategory[];
    currency: string;
}) {
    const maximum = categories.reduce((max, category) => {
        const value = toMinorUnits(amountForCategory(category, currency));
        const absoluteValue = value < 0n ? -value : value;

        return absoluteValue > max ? absoluteValue : max;
    }, 0n);

    if (categories.length === 0 || maximum === 0n) {
        return (
            <ChartEmptyState message={t('No operating expenses recorded.')} />
        );
    }

    const data = categories.map((category) => {
        const amount = amountForCategory(category, currency);

        return {
            category: category.category_label,
            raw: amount,
            value: toPlotValue(amount, maximum),
        };
    });
    const hasNegative = data.some((category) => category.value < 0);
    const height = Math.max(180, data.length * 36);
    const series = [{ key: 'expenses' as const, label: t('Expenses') }];

    return (
        <div
            className="w-full min-w-0"
            style={{ height }}
            role="img"
            aria-label={`${currency} expense breakdown chart`}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={data}
                    layout="vertical"
                    margin={{ top: 4, right: 8, left: 0, bottom: 4 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                        horizontal={false}
                    />
                    <XAxis
                        type="number"
                        domain={chartDomain(hasNegative)}
                        hide
                    />
                    <YAxis
                        type="category"
                        dataKey="category"
                        axisLine={false}
                        tickLine={false}
                        tick={{
                            fill: 'var(--muted-foreground)',
                            fontSize: 11,
                        }}
                        tickFormatter={(value: string) =>
                            value.length > 20 ? `${value.slice(0, 19)}…` : value
                        }
                        width={120}
                    />
                    <Tooltip
                        contentStyle={TOOLTIP_CONTENT_STYLE}
                        itemStyle={TOOLTIP_ITEM_STYLE}
                        labelStyle={{
                            color: 'var(--popover-foreground)',
                            fontWeight: 600,
                            marginBottom: 4,
                        }}
                        formatter={(value, name, item) =>
                            tooltipFormatter(
                                value,
                                name,
                                {
                                    ...item,
                                    dataKey: 'expenses',
                                    payload: {
                                        raw: {
                                            expenses: item.payload?.raw ?? '0',
                                        },
                                    },
                                },
                                currency,
                                series,
                            )
                        }
                        cursor={{
                            fill: 'var(--muted)',
                            opacity: 0.35,
                        }}
                    />
                    <Bar
                        dataKey="value"
                        name={series[0].label}
                        fill={SERIES_COLORS.expenses}
                        radius={[0, 3, 3, 0]}
                        maxBarSize={24}
                        isAnimationActive={false}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
