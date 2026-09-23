import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bath,
    BedDouble,
    Building2,
    Check,
    MapPin,
    Ruler,
} from 'lucide-react';
import PublicListingGallery from '@/components/shared/public-listing-gallery';
import PublicListingHead from '@/components/shared/public-listing-head';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatBillingPeriod, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { index as publicIndex } from '@/routes/public/portal';
import { show as unitTypeShow } from '@/routes/public/portal/unit-types';
import type {
    PublicListing,
    PublicPortalPropertyPageProps,
    PublicUnitType,
    PublicWholePropertyOffering,
} from '@/types';

function locationLabel(listing: PublicListing): string {
    return [
        listing.location.address,
        listing.location.city,
        listing.location.region,
    ]
        .filter(Boolean)
        .join(', ');
}

const billingUnitLabels: Record<string, string> = {
    day: 'Daily',
    week: 'Weekly',
    month: 'Monthly',
    year: 'Yearly',
};

function ratePeriodLabel({
    billing_interval: interval,
    billing_unit: unit,
}: {
    billing_interval: number;
    billing_unit: string;
}): string {
    if (interval === 1) {
        return t(billingUnitLabels[unit] ?? unit);
    }

    return `${t('Every')} ${interval} ${t(`${unit}s`)}`;
}

function UnitTypeDetails({ unitType }: { unitType: PublicUnitType }) {
    return (
        <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
            {unitType.bedrooms !== null && (
                <span className="inline-flex items-center gap-1.5">
                    <BedDouble className="size-4" aria-hidden="true" />
                    {t(':count bedrooms', { count: unitType.bedrooms })}
                </span>
            )}
            {unitType.bathrooms !== null && (
                <span className="inline-flex items-center gap-1.5">
                    <Bath className="size-4" aria-hidden="true" />
                    {unitType.bathrooms} {t('bathrooms')}
                </span>
            )}
            {unitType.size_sqm !== null && (
                <span className="inline-flex items-center gap-1.5">
                    <Ruler className="size-4" aria-hidden="true" />
                    {unitType.size_sqm} m²
                </span>
            )}
        </div>
    );
}

function WholePropertyOffering({
    offering,
}: {
    offering: PublicWholePropertyOffering;
}) {
    return (
        <aside
            aria-labelledby="whole-property-heading"
            className="h-fit rounded-2xl border border-primary/20 bg-card p-5 shadow-sm lg:sticky lg:top-6"
        >
            <div className="space-y-5">
                <div>
                    <p className="text-xs font-medium tracking-wide text-primary uppercase">
                        {t('Rental offering')}
                    </p>
                    <h2
                        id="whole-property-heading"
                        className="mt-1 text-xl font-semibold"
                    >
                        {t('Entire property')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('Rent the property as one offering.')}
                    </p>
                </div>

                <div className="rounded-xl bg-primary/5 px-4 py-4">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t('Starting from')}
                    </p>
                    <p className="mt-1 text-3xl font-semibold tracking-tight">
                        {formatPrice(
                            offering.starting_price.amount,
                            offering.starting_price.currency,
                        )}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {ratePeriodLabel(offering.starting_price)}
                    </p>
                </div>

                <div className="space-y-3">
                    <h3 className="text-sm font-semibold">
                        {t('Pricing options')}
                    </h3>
                    <dl className="divide-y divide-border/70 border-y border-border/70">
                        {offering.rates.map((price) => (
                            <div
                                key={`${price.currency}-${price.billing_unit}-${price.billing_interval}`}
                                className="flex items-center justify-between gap-4 py-3"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {ratePeriodLabel(price)}
                                </dt>
                                <dd className="text-right text-sm font-semibold tabular-nums">
                                    {formatPrice(price.amount, price.currency)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="space-y-3 border-t pt-4">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-sm font-semibold">
                            {t('Availability')}
                        </span>
                        <Badge variant="outline">
                            {offering.availability === 'available_for_inquiry'
                                ? t('Available for inquiry')
                                : t('Unavailable')}
                        </Badge>
                    </div>
                    <p className="flex gap-2 text-xs leading-5 text-muted-foreground">
                        <Check
                            className="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {t(
                            'Date-specific availability is not currently calculated.',
                        )}
                    </p>
                </div>
            </div>
        </aside>
    );
}

export default function Show({
    listing,
    metadata,
}: PublicPortalPropertyPageProps) {
    const location = locationLabel(listing);
    const unitTypes = listing.unit_types ?? [];
    const inventory = listing.inventory;
    const wholePropertyOffering = listing.whole_property_offering;

    return (
        <>
            <PublicListingHead
                metadata={metadata}
            />

            <div className="mx-auto w-full max-w-7xl space-y-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <nav aria-label="Breadcrumb" className="text-sm">
                    <Link
                        href={publicIndex()}
                        className="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        {t('All properties')}
                    </Link>
                    <span className="mx-2 text-muted-foreground">/</span>
                    <span>{listing.name}</span>
                </nav>

                <header className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{listing.type_label}</Badge>
                        {listing.rental_mode !== 'unit' && (
                            <Badge variant="outline">
                                {listing.rental_mode === 'whole_property'
                                    ? t('Entire property')
                                    : t('Entire property + unit types')}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                        {listing.name}
                    </h1>
                    {location && (
                        <p className="flex items-start gap-2 text-base text-muted-foreground">
                            <MapPin
                                className="mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            {location}
                        </p>
                    )}
                </header>

                <PublicListingGallery items={listing.gallery} />

                <div className="grid items-start gap-12 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-10">
                        {listing.description && (
                            <section
                                aria-labelledby="about-heading"
                                className="space-y-3"
                            >
                                <h2
                                    id="about-heading"
                                    className="text-2xl font-semibold"
                                >
                                    {t('About this property')}
                                </h2>
                                <p className="max-w-3xl text-base leading-7 whitespace-pre-wrap text-muted-foreground">
                                    {listing.description}
                                </p>
                            </section>
                        )}

                        {listing.amenities.length > 0 && (
                            <section
                                aria-labelledby="amenities-heading"
                                className="space-y-4"
                            >
                                <h2
                                    id="amenities-heading"
                                    className="text-2xl font-semibold"
                                >
                                    {t('Amenities')}
                                </h2>
                                <ul className="grid gap-x-8 gap-y-1 sm:grid-cols-2">
                                    {listing.amenities.map((amenity) => (
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

                        {listing.rental_mode !== 'whole_property' &&
                            (listing.rental_mode === 'unit' ||
                                unitTypes.length > 0) && (
                                <section
                                    aria-labelledby="unit-types-heading"
                                    className="space-y-4"
                                >
                                    <div>
                                        <h2
                                            id="unit-types-heading"
                                            className="text-2xl font-semibold"
                                        >
                                            {t('Available unit types')}
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {t(
                                                'Choose the space that suits you best.',
                                            )}
                                        </p>
                                    </div>
                                    {unitTypes.length > 0 ? (
                                        <div className="grid gap-5 md:grid-cols-2">
                                            {unitTypes.map((unitType) => {
                                                const cover =
                                                    unitType.gallery[0];
                                                const startingPrice =
                                                    unitType.starting_prices[0];

                                                return (
                                                    <Link
                                                        key={unitType.slug}
                                                        href={unitTypeShow({
                                                            property:
                                                                listing.slug,
                                                            unitType:
                                                                unitType.slug,
                                                        })}
                                                        className="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        <Card className="h-full overflow-hidden py-0 transition-shadow group-hover:shadow-lg">
                                                            <div className="aspect-[4/3] overflow-hidden bg-muted">
                                                                {cover ? (
                                                                    <img
                                                                        src={
                                                                            cover.url
                                                                        }
                                                                        alt={
                                                                            cover.alt ||
                                                                            unitType.name
                                                                        }
                                                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                                    />
                                                                ) : (
                                                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                                                        <Building2
                                                                            className="size-12"
                                                                            aria-hidden="true"
                                                                        />
                                                                        <span className="sr-only">
                                                                            {t(
                                                                                'No photos available',
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <CardHeader className="gap-3 px-5 pt-5">
                                                                <CardTitle className="flex items-start justify-between gap-3 text-xl">
                                                                    <span>
                                                                        {
                                                                            unitType.name
                                                                        }
                                                                    </span>
                                                                    <ArrowRight
                                                                        className="mt-1 size-5 shrink-0 transition-transform group-hover:translate-x-1"
                                                                        aria-hidden="true"
                                                                    />
                                                                </CardTitle>
                                                                <UnitTypeDetails
                                                                    unitType={
                                                                        unitType
                                                                    }
                                                                />
                                                            </CardHeader>
                                                            <CardContent className="space-y-3 px-5 pt-0 pb-5">
                                                                {unitType.furnishing && (
                                                                    <p className="text-sm text-muted-foreground capitalize">
                                                                        {
                                                                            unitType.furnishing
                                                                        }
                                                                    </p>
                                                                )}
                                                                <div className="flex flex-wrap items-end justify-between gap-3 border-t pt-4">
                                                                    <div>
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {startingPrice
                                                                                ? t(
                                                                                      'Starting from',
                                                                                  )
                                                                                : t(
                                                                                      'Pricing unavailable',
                                                                                  )}
                                                                        </p>
                                                                        {startingPrice && (
                                                                            <p className="font-semibold">
                                                                                {formatPrice(
                                                                                    startingPrice.amount,
                                                                                    startingPrice.currency,
                                                                                )}{' '}
                                                                                <span className="font-normal text-muted-foreground">
                                                                                    {formatBillingPeriod(
                                                                                        startingPrice.billing_interval,
                                                                                        startingPrice.billing_unit,
                                                                                    )}
                                                                                </span>
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                    <p className="text-sm font-medium text-muted-foreground">
                                                                        {unitType
                                                                            .inventory
                                                                            .available_units >
                                                                        0
                                                                            ? t(
                                                                                  ':count available',
                                                                                  {
                                                                                      count: unitType
                                                                                          .inventory
                                                                                          .available_units,
                                                                                  },
                                                                              )
                                                                            : t(
                                                                                  'Unavailable',
                                                                              )}
                                                                    </p>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <Card>
                                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                                {t(
                                                    'No published unit types are available yet.',
                                                )}
                                            </CardContent>
                                        </Card>
                                    )}
                                </section>
                            )}
                    </div>

                    {wholePropertyOffering && listing.rental_mode !== 'unit' ? (
                        <WholePropertyOffering
                            offering={wholePropertyOffering}
                        />
                    ) : (
                        <aside className="h-fit rounded-2xl border bg-card p-5 lg:sticky lg:top-6">
                            <div className="space-y-4">
                                <h2 className="font-semibold">
                                    {t('At a glance')}
                                </h2>
                                <dl className="grid gap-4 text-sm">
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('Unit types')}
                                        </dt>
                                        <dd className="font-medium">
                                            {unitTypes.length}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('Total units')}
                                        </dt>
                                        <dd className="font-medium">
                                            {inventory?.total_units ?? 0}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('Available now')}
                                        </dt>
                                        <dd className="font-medium">
                                            {inventory?.available_units ?? 0}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}
