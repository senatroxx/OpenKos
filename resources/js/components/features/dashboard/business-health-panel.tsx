import { AlertCircle, Building2, TrendingUp } from 'lucide-react';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Finance } from '@/types';
import { CurrencyAmountList } from './currency-amount-list';

function amountSize(formattedAmount: string): string {
    if (formattedAmount.length > 20) {
        return 'text-lg sm:text-xl';
    }

    if (formattedAmount.length > 14) {
        return 'text-xl sm:text-2xl';
    }

    return 'text-2xl sm:text-3xl';
}

function moneyAmountClass(
    groups: Finance['revenue_this_month'],
    colorClassName: string,
): string {
    const size =
        groups.length === 1
            ? amountSize(formatPrice(groups[0].amount, groups[0].currency))
            : 'text-sm sm:text-base';

    return cn(colorClassName, 'font-bold tabular-nums', size);
}

function RateProgress({ rate }: { rate: number }) {
    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
                className="h-full rounded-full bg-primary transition-all duration-300"
                style={{
                    width: `${Math.min(100, Math.max(0, rate))}%`,
                }}
            />
        </div>
    );
}

function CollectionRateValue({ rates }: { rates: Finance['collection_rate'] }) {
    if (rates.length === 0) {
        return (
            <p className="text-2xl font-bold text-primary tabular-nums sm:text-3xl">
                —
            </p>
        );
    }

    if (rates.length === 1) {
        return (
            <>
                <p className="text-2xl font-bold text-primary tabular-nums sm:text-3xl">
                    {rates[0].currency} {rates[0].rate}%
                </p>
                <div className="mt-2.5">
                    <RateProgress rate={rates[0].rate} />
                </div>
            </>
        );
    }

    return (
        <div className="mt-2 flex flex-col gap-2">
            {rates.map((rate) => (
                <div
                    key={rate.currency}
                    className="flex items-center gap-2 text-sm font-bold tabular-nums"
                >
                    <span className="w-8 shrink-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {rate.currency}
                    </span>
                    <span className="w-8 shrink-0 text-right text-primary">
                        {rate.rate}%
                    </span>
                    <div className="min-w-0 flex-1">
                        <RateProgress rate={rate.rate} />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function BusinessHealthPanel({ finance }: { finance: Finance }) {
    return (
        <section className="mb-10 flex flex-col gap-3">
            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {t('Business Health')}
            </h2>
            <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                <div className="grid gap-3 divide-y divide-border sm:grid-cols-2 sm:gap-4 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                    <div className="pt-2 pb-2 first:pl-0 sm:px-4 sm:pt-0 sm:pb-0 sm:pl-0">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <TrendingUp className="size-3.5 text-surface-green-foreground" />
                            <span>{t('Revenue This Month')}</span>
                        </div>
                        <CurrencyAmountList
                            groups={finance.revenue_this_month}
                            amountClassName={moneyAmountClass(
                                finance.revenue_this_month,
                                'text-surface-green-foreground',
                            )}
                            className={
                                finance.revenue_this_month.length > 1
                                    ? 'mt-1'
                                    : undefined
                            }
                        />
                    </div>
                    <div className="pt-3 pb-2 sm:px-4 sm:pt-0 sm:pb-0">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Building2 className="size-3.5 text-muted-foreground" />
                            <span>{t('Monthly Potential')}</span>
                        </div>
                        <CurrencyAmountList
                            groups={finance.monthly_potential}
                            amountClassName={moneyAmountClass(
                                finance.monthly_potential,
                                'text-foreground',
                            )}
                            className={
                                finance.monthly_potential.length > 1
                                    ? 'mt-1'
                                    : undefined
                            }
                        />
                    </div>
                    <div className="pt-3 pb-2 sm:px-4 sm:pt-0 sm:pb-0 sm:pl-0 xl:pl-4">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <AlertCircle className="size-3.5 text-surface-red-foreground" />
                            <span>{t('Outstanding')}</span>
                        </div>
                        <CurrencyAmountList
                            groups={finance.outstanding}
                            amountClassName={moneyAmountClass(
                                finance.outstanding,
                                'text-surface-red-foreground',
                            )}
                            className={
                                finance.outstanding.length > 1
                                    ? 'mt-1'
                                    : undefined
                            }
                        />
                    </div>
                    <div className="pt-3 sm:px-4 sm:pt-0">
                        <div className="mb-1.5 flex items-center justify-between text-xs font-medium text-muted-foreground">
                            <span>{t('Collection Rate')}</span>
                        </div>
                        <CollectionRateValue rates={finance.collection_rate} />
                    </div>
                </div>
            </div>
        </section>
    );
}
