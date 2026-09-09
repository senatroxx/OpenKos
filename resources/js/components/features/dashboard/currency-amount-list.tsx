import { formatPrice } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MoneyAggregate } from '@/types';

type CurrencyAmountListProps = {
    groups: MoneyAggregate[];
    amountClassName?: string;
    className?: string;
    compact?: boolean;
};

function formatCompactAmount(group: MoneyAggregate): string {
    const formatted = formatPrice(group.amount, group.currency);

    return formatted.replace(new RegExp(`^${group.currency}\\s*`), '');
}

export function CurrencyAmountList({
    groups,
    amountClassName,
    className,
    compact = false,
}: CurrencyAmountListProps) {
    if (groups.length === 0) {
        return (
            <span
                className={cn(
                    compact && 'text-sm font-semibold tabular-nums',
                    className,
                    amountClassName,
                )}
            >
                —
            </span>
        );
    }

    if (groups.length === 1 && !compact) {
        return (
            <span className={cn(className, amountClassName)}>
                {formatPrice(groups[0].amount, groups[0].currency)}
            </span>
        );
    }

    return (
        <span className={cn('flex flex-col gap-0.5', className)}>
            {groups.map((group) => (
                <span
                    key={group.currency}
                    className="flex min-w-0 items-baseline gap-3 leading-none"
                >
                    <span className="w-9 shrink-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {group.currency}
                    </span>
                    <span
                        className={cn(
                            compact
                                ? 'ml-auto min-w-0 text-right text-xs leading-tight font-semibold whitespace-nowrap tabular-nums sm:text-sm'
                                : 'min-w-0 text-right text-sm leading-tight font-bold break-words tabular-nums sm:text-base',
                            amountClassName,
                        )}
                    >
                        {compact
                            ? formatCompactAmount(group)
                            : formatPrice(group.amount, group.currency)}
                    </span>
                </span>
            ))}
        </span>
    );
}
