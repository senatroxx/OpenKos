import { Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import PublicListingGallery from '@/components/shared/public-listing-gallery';
import PublicListingHead from '@/components/shared/public-listing-head';
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { create as applicationCreate } from '@/routes/applications';
import { show as propertyShow } from '@/routes/public/portal';
import type {
    PublicPricingOptionsProps,
    PublicRentalSummaryProps,
    PublicStartingPrice,
    PublicUnitTypeAttributesProps,
    PublicUnitTypePageProps,
} from '@/types';

function displayNumber(value: number | string): string {
    return String(Number(value));
}

function bedroomLabel(count: number): string {
    return `${displayNumber(count)} ${count === 1 ? t('bedroom') : t('bedrooms')}`;
}

function bathroomLabel(value: string): string {
    const count = Number(value);

    return `${displayNumber(value)} ${count === 1 ? t('bathroom') : t('bathrooms')}`;
}

function billingLabel(price: PublicStartingPrice): string {
    return price.billing_label.replace(/^\/\s*/, `${t('per')} `);
}

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

function UnitTypeAttributes({ unitType }: PublicUnitTypeAttributesProps) {
    const attributes = [
        unitType.bedrooms !== null ? bedroomLabel(unitType.bedrooms) : null,
        unitType.bathrooms !== null ? bathroomLabel(unitType.bathrooms) : null,
        unitType.size_sqm !== null
            ? `${displayNumber(unitType.size_sqm)} m²`
            : null,
        unitType.furnishing ? unitType.furnishing : null,
    ].filter(Boolean);

    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
            {attributes.map((attribute, index) => (
                <span key={attribute} className="capitalize">
                    {index > 0 && <span className="mr-2">·</span>}
                    {attribute}
                </span>
            ))}
        </div>
    );
}

function PricingOptions({ prices }: PublicPricingOptionsProps) {
    const groups = priceGroups(prices);

    if (prices.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('Pricing is not available for this unit type yet.')}
            </p>
        );
    }

    return (
        <div className="divide-y divide-border/70 border-y border-border/70">
            {Object.entries(groups).map(([currency, currencyPrices]) => (
                <div key={currency} className="py-4 first:pt-0 last:pb-0">
                    <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {currency}
                    </p>
                    <dl className="space-y-2">
                        {currencyPrices.map((price) => (
                            <div
                                key={`${price.currency}-${price.billing_unit}-${price.billing_interval}`}
                                className="flex items-baseline justify-between gap-4 text-sm"
                            >
                                <dt className="text-muted-foreground">
                                    {billingLabel(price)}
                                </dt>
                                <dd className="text-right font-semibold tabular-nums">
                                    {formatPrice(price.amount, price.currency)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            ))}
        </div>
    );
}

function RentalSummary({ unitType, propertySlug }: PublicRentalSummaryProps) {
    const startingPrice = unitType.starting_prices[0];
    const availableUnits = unitType.inventory.available_units;
    const hasAvailability = availableUnits > 0;

    return (
        <aside className="h-fit rounded-xl border bg-card p-5 shadow-sm lg:sticky lg:top-6">
            <div className="space-y-5">
                <div>
                    <p className="text-sm text-muted-foreground">{t('From')}</p>
                    {startingPrice ? (
                        <>
                            <p className="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                                {formatPrice(
                                    startingPrice.amount,
                                    startingPrice.currency,
                                )}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {billingLabel(startingPrice)}
                            </p>
                        </>
                    ) : (
                        <p className="mt-1 text-lg font-semibold">
                            {t('Pricing unavailable')}
                        </p>
                    )}
                </div>

                <div className="border-t pt-4">
                    <p className="flex items-center gap-2 text-sm font-medium">
                        <Check
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />
                        {hasAvailability
                            ? t('Available now')
                            : t('Currently full')}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {hasAvailability
                            ? t(':count unit available', {
                                  count: availableUnits,
                              })
                            : t('Applications are still being accepted.')}
                    </p>
                </div>

                <Link
                    href={applicationCreate({
                        query: {
                            target_type: 'unit_type',
                            property_slug: propertySlug,
                            unit_type_slug: unitType.slug,
                        },
                    })}
                    className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {t('Apply for this unit type')}
                </Link>
            </div>
        </aside>
    );
}

export default function UnitType({
    listing,
    canonicalUrl,
}: PublicUnitTypePageProps) {
    const unitType = listing.unit_type;
    const title =
        unitType.bedrooms !== null &&
        !/^\d+(?:\.\d+)?\s*BD\b/i.test(unitType.name)
            ? `${displayNumber(unitType.bedrooms)}BD ${unitType.name}`
            : unitType.name;

    return (
        <>
            <PublicListingHead
                title={`${unitType.name} - ${listing.property.name}`}
                description={unitType.description}
                canonicalUrl={canonicalUrl}
                imageUrl={unitType.gallery[0]?.url}
            />

            <div className="mx-auto w-full max-w-7xl space-y-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <nav aria-label="Breadcrumb" className="text-sm">
                    <Link
                        href={propertyShow({ property: listing.property.slug })}
                        className="inline-flex min-h-11 items-center gap-2 text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        {listing.property.name}
                    </Link>
                </nav>

                <header className="space-y-3">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                        {title}
                    </h1>
                    <UnitTypeAttributes unitType={unitType} />
                </header>

                <PublicListingGallery items={unitType.gallery} />

                <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-14">
                    <div className="min-w-0 space-y-10">
                        {unitType.description && (
                            <section
                                aria-labelledby="about-heading"
                                className="space-y-3"
                            >
                                <h2
                                    id="about-heading"
                                    className="text-2xl font-semibold"
                                >
                                    {t('About this unit type')}
                                </h2>
                                <p className="max-w-3xl text-base leading-7 whitespace-pre-wrap text-muted-foreground">
                                    {unitType.description}
                                </p>
                            </section>
                        )}

                        {unitType.amenities.length > 0 && (
                            <section
                                aria-labelledby="amenities-heading"
                                className="space-y-4"
                            >
                                <h2
                                    id="amenities-heading"
                                    className="text-2xl font-semibold"
                                >
                                    {t('Features and amenities')}
                                </h2>
                                <ul className="grid gap-x-8 gap-y-1 sm:grid-cols-2">
                                    {unitType.amenities.map((amenity) => (
                                        <li
                                            key={amenity.name}
                                            className="flex items-center gap-3 border-b border-border/60 py-3 text-sm"
                                        >
                                            <AmenityIcon
                                                icon={amenity.icon}
                                                className="size-5 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>{amenity.name}</span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        <section
                            aria-labelledby="pricing-heading"
                            className="space-y-4"
                        >
                            <h2
                                id="pricing-heading"
                                className="text-2xl font-semibold"
                            >
                                {t('Pricing options')}
                            </h2>
                            <PricingOptions prices={unitType.starting_prices} />
                        </section>
                    </div>

                    <RentalSummary
                        unitType={unitType}
                        propertySlug={listing.property.slug}
                    />
                </div>
            </div>
        </>
    );
}
