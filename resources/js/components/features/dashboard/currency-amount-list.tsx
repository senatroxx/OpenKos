import { formatPrice } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MoneyAggregate } from '@/types';

type CurrencyAmountListProps = {
    groups: MoneyAggregate[];
    amountClassName?: string;
    className?: string;
};

export function CurrencyAmountList({
    groups,
    amountClassName,
    className,
}: CurrencyAmountListProps) {
    if (groups.length === 0) {
        return <span className={cn(className, amountClassName)}>—</span>;
    }

    if (groups.length === 1) {
        return (
            <span className={cn(className, amountClassName)}>
                {formatPrice(groups[0].amount, groups[0].currency)}
            </span>
        );
    }

    return (
        <div className={cn('flex flex-col gap-0.5', className)}>
            {groups.map((group) => (
                <div
                    key={group.currency}
                    className="flex min-w-0 items-baseline gap-3 leading-none"
                >
                    <span className="w-9 shrink-0 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {group.currency}
                    </span>
                    <span
                        className={cn(
                            'min-w-0 text-right text-sm leading-tight font-bold break-words tabular-nums sm:text-base',
                            amountClassName,
                        )}
                    >
                        {formatPrice(group.amount, group.currency)}
                    </span>
                </div>
            ))}
        </div>
    );
}
