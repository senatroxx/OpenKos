import { formatPrice } from '@/lib/formatters';
import { cn } from '@/lib/utils';
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

type FinancialTrendChartProps = {
    points: FinancialChartPoint[];
    currency: string;
    series: Array<{
        key: SeriesKey;
        label: string;
        className: string;
    }>;
};

const MONEY_SCALE = 3;

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

function amountFor(
    point: FinancialChartPoint,
    key: SeriesKey,
    currency: string,
): string {
    return (
        point[key]?.find((group) => group.currency === currency)?.amount ?? '0'
    );
}

function barWidth(amount: string, maximum: bigint): string {
    if (maximum === 0n) {
        return '0%';
    }

    const value = toMinorUnits(amount);
    const absoluteValue = value < 0n ? -value : value;
    const percentage = (absoluteValue * 10000n) / maximum;
    const whole = percentage / 100n;
    const fraction = (percentage % 100n).toString().padStart(2, '0');

    return `${whole}.${fraction}%`;
}

export function FinancialTrendChart({
    points,
    currency,
    series,
}: FinancialTrendChartProps) {
    const maximum = points.reduce((max, point) => {
        return series.reduce((seriesMax, item) => {
            const value = toMinorUnits(amountFor(point, item.key, currency));

            return value < 0n
                ? seriesMax > -value
                    ? seriesMax
                    : -value
                : seriesMax > value
                  ? seriesMax
                  : value;
        }, max);
    }, 0n);

    if (points.length === 0 || series.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No financial activity for this period.
            </p>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                {series.map((item) => (
                    <span
                        key={item.key}
                        className="inline-flex items-center gap-1.5"
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                item.className,
                            )}
                        />
                        {item.label}
                    </span>
                ))}
            </div>
            <div
                className="space-y-4"
                role="img"
                aria-label={`${currency} financial trend chart`}
            >
                {points.map((point) => (
                    <div
                        key={point.month}
                        className="grid gap-2 sm:grid-cols-[5rem_1fr] sm:items-center"
                    >
                        <span className="text-xs font-medium text-muted-foreground tabular-nums">
                            {point.label}
                        </span>
                        <div className="space-y-1.5">
                            {series.map((item) => {
                                const amount = amountFor(
                                    point,
                                    item.key,
                                    currency,
                                );

                                return (
                                    <div
                                        key={item.key}
                                        className="grid grid-cols-[5rem_1fr_auto] items-center gap-2"
                                    >
                                        <span className="truncate text-xs text-muted-foreground sm:hidden">
                                            {item.label}
                                        </span>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted sm:col-span-2">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-[width]',
                                                    item.className,
                                                )}
                                                style={{
                                                    width: barWidth(
                                                        amount,
                                                        maximum,
                                                    ),
                                                }}
                                            />
                                        </div>
                                        <span className="text-right text-xs font-medium whitespace-nowrap tabular-nums">
                                            {formatPrice(amount, currency)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
