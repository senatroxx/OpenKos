import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Finance, MoneyAggregate } from '@/types';
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

function moneyAmountClass(groups: MoneyAggregate[]): string {
    const size =
        groups.length === 1
            ? amountSize(formatPrice(groups[0].amount, groups[0].currency))
            : 'text-sm sm:text-base';

    return cn('font-bold tabular-nums', size);
}

function ExpenseChangeList({ groups }: { groups: MoneyAggregate[] }) {
    const amountClassName = moneyAmountClass(groups);

    if (groups.length === 0) {
        return <span className={amountClassName}>—</span>;
    }

    if (groups.length === 1) {
        const group = groups[0];
        const isZero = /^-?0(?:\.0+)?$/.test(group.amount);
        const prefix = group.amount.startsWith('-') || isZero ? '' : '+';

        return (
            <span
                className={cn(
                    amountClassName,
                    group.amount.startsWith('-')
                        ? 'text-surface-green-foreground'
                        : isZero
                          ? 'text-foreground'
                          : 'text-surface-red-foreground',
                )}
            >
                {prefix}
                {formatPrice(group.amount, group.currency)}
            </span>
        );
    }

    return (
        <span className="flex flex-col gap-0.5">
            {groups.map((group) => {
                const isZero = /^-?0(?:\.0+)?$/.test(group.amount);
                const prefix =
                    group.amount.startsWith('-') || isZero ? '' : '+';

                return (
                    <span
                        key={group.currency}
                        className="flex min-w-0 items-baseline gap-3 leading-none"
                    >
                        <span className="w-9 shrink-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.currency}
                        </span>
                        <span
                            className={cn(
                                'min-w-0 leading-tight break-words',
                                amountClassName,
                                group.amount.startsWith('-')
                                    ? 'text-surface-green-foreground'
                                    : isZero
                                      ? 'text-foreground'
                                      : 'text-surface-red-foreground',
                            )}
                        >
                            {prefix}
                            {formatPrice(group.amount, group.currency)}
                        </span>
                    </span>
                );
            })}
        </span>
    );
}

export function ExpensesSummaryPanel({
    expenses,
}: {
    expenses: Finance['expenses'];
}) {
    return (
        <section className="mb-10 flex flex-col gap-3">
            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {t('Expenses')}
            </h2>
            <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                <div className="grid gap-3 divide-y divide-border sm:grid-cols-3 sm:gap-4 sm:divide-x sm:divide-y-0">
                    <div className="pb-3 sm:pr-4 sm:pb-0">
                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                            {t('Expenses This Month')}
                        </p>
                        <CurrencyAmountList
                            groups={expenses.this_month}
                            amountClassName={moneyAmountClass(
                                expenses.this_month,
                            )}
                        />
                    </div>
                    <div className="border-border pt-3 pb-3 sm:px-4 sm:pt-0 sm:pb-0">
                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                            {t('Expenses Last Month')}
                        </p>
                        <CurrencyAmountList
                            groups={expenses.last_month}
                            amountClassName={moneyAmountClass(
                                expenses.last_month,
                            )}
                        />
                    </div>
                    <div className="border-border pt-3 sm:pt-0 sm:pl-4">
                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                            {t('Change vs Last Month')}
                        </p>
                        <ExpenseChangeList
                            groups={expenses.change_vs_last_month}
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
