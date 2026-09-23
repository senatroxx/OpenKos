import { formatBillingOptionLabel, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { PublicPricingOptionsProps, PublicStartingPrice } from '@/types';

function priceGroups(
    prices: PublicStartingPrice[],
): Record<string, PublicStartingPrice[]> {
    return prices.reduce<Record<string, PublicStartingPrice[]>>(
        (groups, price) => {
            (groups[price.currency] ??= []).push(price);

            return groups;
        },
        {},
    );
}

export default function PublicPricingOptions({
    prices,
}: PublicPricingOptionsProps) {
    const groups = priceGroups(prices);

    return (
        <div className="space-y-5">
            {Object.entries(groups).map(([currency, currencyPrices]) => (
                <section key={currency} className="border-t pt-3 first:border-t-0 first:pt-0">
                    <h3 className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {currency}
                    </h3>
                    <dl>
                        {currencyPrices.map((price) => (
                            <div
                                key={`${price.currency}-${price.billing_unit}-${price.billing_interval}`}
                                className="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-6 border-b border-border/60 py-2 last:border-b-0"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {formatBillingOptionLabel(
                                        price.billing_interval,
                                        price.billing_unit,
                                    )}
                                </dt>
                                <dd className="text-right text-sm font-semibold tabular-nums">
                                    {formatPrice(price.amount, price.currency)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ))}
            {prices.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('Pricing is not available yet.')}
                </p>
            )}
        </div>
    );
}
