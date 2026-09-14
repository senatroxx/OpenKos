import { Link } from '@inertiajs/react';
import { ArrowLeft, Bath, BedDouble, Check, Ruler } from 'lucide-react';
import PublicListingGallery from '@/components/shared/public-listing-gallery';
import PublicListingHead from '@/components/shared/public-listing-head';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatBillingPeriod, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { show as propertyShow } from '@/routes/public/portal';
import type { PublicUnitTypePage } from '@/types';

export default function UnitType({
    listing,
    canonicalUrl,
}: {
    listing: PublicUnitTypePage;
    canonicalUrl: string;
}) {
    const unitType = listing.unit_type;

    return (
        <>
            <PublicListingHead
                title={`${unitType.name} - ${listing.property.name}`}
                description={unitType.description}
                canonicalUrl={canonicalUrl}
                imageUrl={unitType.gallery[0]?.url}
            />

            <div className="mx-auto w-full max-w-6xl space-y-10 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <nav aria-label="Breadcrumb" className="text-sm">
                    <Link
                        href={propertyShow({ property: listing.property.slug })}
                        className="inline-flex items-center gap-2 text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        {listing.property.name}
                    </Link>
                    <span className="mx-2 text-muted-foreground">/</span>
                    <span>{unitType.name}</span>
                </nav>

                <header className="space-y-4">
                    <Badge variant="secondary">{t('Unit type')}</Badge>
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                        {unitType.name}
                    </h1>
                    <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                        {unitType.bedrooms !== null && (
                            <span className="inline-flex items-center gap-1.5">
                                <BedDouble
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {t(':count bedrooms', {
                                    count: unitType.bedrooms,
                                })}
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
                        {unitType.furnishing && (
                            <span className="capitalize">
                                {unitType.furnishing}
                            </span>
                        )}
                    </div>
                </header>

                <PublicListingGallery items={unitType.gallery} />

                <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-10">
                        {unitType.description && (
                            <section
                                aria-labelledby="description-heading"
                                className="space-y-3"
                            >
                                <h2
                                    id="description-heading"
                                    className="text-2xl font-semibold"
                                >
                                    {t('About this unit type')}
                                </h2>
                                <p className="text-base leading-7 whitespace-pre-wrap text-muted-foreground">
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
                                <ul className="grid gap-3 sm:grid-cols-2">
                                    {unitType.amenities.map((amenity) => (
                                        <li
                                            key={amenity.name}
                                            className="flex items-center gap-3 rounded-lg border bg-card px-4 py-3"
                                        >
                                            <AmenityIcon
                                                icon={amenity.icon}
                                                className="size-5 text-primary"
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
                                {t('Pricing')}
                            </h2>
                            {unitType.starting_prices.length > 0 ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {unitType.starting_prices.map((price) => (
                                        <Card
                                            key={`${price.currency}-${price.billing_unit}-${price.billing_interval}`}
                                        >
                                            <CardHeader className="px-5 pt-5 pb-0">
                                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                                    {formatBillingPeriod(
                                                        price.billing_interval,
                                                        price.billing_unit,
                                                    )}
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent className="px-5 pt-2 pb-5">
                                                <p className="text-2xl font-semibold">
                                                    {formatPrice(
                                                        price.amount,
                                                        price.currency,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {price.billing_label}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="py-10 text-sm text-muted-foreground">
                                        {t(
                                            'Pricing is not available for this unit type yet.',
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </section>
                    </div>

                    <aside className="h-fit rounded-2xl border bg-card p-5 lg:sticky lg:top-6">
                        <div className="space-y-4">
                            <h2 className="font-semibold">
                                {t('Availability')}
                            </h2>
                            <dl className="grid gap-4 text-sm">
                                <div className="flex items-center justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        {t('Total units')}
                                    </dt>
                                    <dd className="font-medium">
                                        {unitType.inventory.total_units}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        {t('Available now')}
                                    </dt>
                                    <dd className="font-medium">
                                        {unitType.inventory.available_units}
                                    </dd>
                                </div>
                            </dl>
                            <p className="flex gap-2 border-t pt-4 text-xs leading-5 text-muted-foreground">
                                <Check
                                    className="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                {unitType.inventory.available_units > 0
                                    ? t(
                                          'This unit type currently has availability.',
                                      )
                                    : t(
                                          'This unit type is currently unavailable.',
                                      )}
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
