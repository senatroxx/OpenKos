import { Link } from '@inertiajs/react';
import { ArrowRight, Building2, MapPin, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import PublicListingHead from '@/components/shared/public-listing-head';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatBillingPeriod, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { show as propertyShow } from '@/routes/public/portal';
import type { PublicListing, PublicStartingPrice } from '@/types';

function locationLabel(listing: PublicListing): string {
    return [
        listing.location.address,
        listing.location.city,
        listing.location.region,
    ]
        .filter(Boolean)
        .join(', ');
}

function firstStartingPrice(
    listing: PublicListing,
): PublicStartingPrice | null {
    return (
        listing.unit_types.find(
            (unitType) => unitType.starting_prices.length > 0,
        )?.starting_prices[0] ?? null
    );
}

function propertyCountLabel(count: number): string {
    return count === 1 ? t('1 property') : t(':count properties', { count });
}

export default function Index({
    listings,
    canonicalUrl,
}: {
    listings: PublicListing[];
    canonicalUrl: string;
}) {
    const [search, setSearch] = useState('');
    const normalizedSearch = search.trim().toLocaleLowerCase();
    const filteredListings = useMemo(
        () =>
            listings.filter((listing) => {
                if (!normalizedSearch) {
                    return true;
                }

                return [
                    listing.name,
                    listing.type_label,
                    listing.location.address,
                    listing.location.city,
                    listing.location.region,
                ].some((value) =>
                    value?.toLocaleLowerCase().includes(normalizedSearch),
                );
            }),
        [listings, normalizedSearch],
    );

    return (
        <>
            <PublicListingHead
                title={t('Available properties')}
                description={t(
                    'Explore our published properties and available unit types.',
                )}
                canonicalUrl={canonicalUrl}
                imageUrl={listings[0]?.gallery[0]?.url}
            />

            <div className="mx-auto w-full max-w-[1360px] px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <section className="relative isolate overflow-hidden rounded-3xl border border-primary/15 bg-gradient-to-br from-primary/10 via-background to-secondary/60 px-6 py-8 shadow-sm sm:px-10 sm:py-10">
                    <div
                        className="pointer-events-none absolute -top-24 -right-16 -z-10 size-64 rounded-full bg-primary/10 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute right-8 bottom-0 -z-10 hidden size-24 rotate-12 rounded-3xl border border-primary/15 sm:block"
                        aria-hidden="true"
                    />
                    <div className="relative max-w-2xl space-y-3">
                        <p className="text-sm font-medium tracking-wide text-primary uppercase">
                            {t('Find your next place')}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            {t('Comfortable spaces, ready for you.')}
                        </h1>
                        <p className="max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">
                            {t(
                                'Browse our properties and discover the unit type that fits your needs.',
                            )}
                        </p>
                    </div>
                </section>

                <section
                    className="mt-10 space-y-6"
                    aria-labelledby="listings-heading"
                >
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h2
                                    id="listings-heading"
                                    className="text-2xl font-semibold tracking-tight sm:text-3xl"
                                >
                                    {t('Explore properties')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {normalizedSearch
                                        ? t(':count of :total properties', {
                                              count: filteredListings.length,
                                              total: listings.length,
                                          })
                                        : propertyCountLabel(listings.length)}
                                </p>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('Find a place that feels like home.')}
                            </p>
                        </div>
                        <div
                            className="w-full rounded-2xl border bg-card p-2 shadow-xs sm:p-3 lg:max-w-xl"
                            role="search"
                            aria-label={t('Property discovery')}
                        >
                            <div className="relative">
                                <label
                                    htmlFor="property-search"
                                    className="sr-only"
                                >
                                    {t('Search properties')}
                                </label>
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="property-search"
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder={t(
                                        'Search by property or location',
                                    )}
                                    className="h-10 border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0"
                                />
                            </div>
                        </div>
                    </div>

                    {filteredListings.length > 0 ? (
                        <div
                            className={cn(
                                'grid grid-cols-1 gap-6',
                                filteredListings.length === 1
                                    ? 'mx-auto max-w-[27rem]'
                                    : filteredListings.length === 2
                                      ? 'mx-auto sm:max-w-[56rem] sm:grid-cols-2'
                                      : 'sm:grid-cols-2 xl:grid-cols-3',
                            )}
                        >
                            {filteredListings.map((listing) => {
                                const cover = listing.gallery[0];
                                const location = locationLabel(listing);
                                const startingPrice =
                                    firstStartingPrice(listing);
                                const availableUnits =
                                    listing.inventory.available_units;

                                return (
                                    <Link
                                        key={listing.slug}
                                        href={propertyShow({
                                            property: listing.slug,
                                        })}
                                        className="group block h-full max-w-[27rem] min-w-0 justify-self-center rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <Card className="h-full overflow-hidden border-border/70 py-0 shadow-sm transition-[border-color,box-shadow,transform] duration-200 group-hover:-translate-y-0.5 group-hover:border-primary/40 group-hover:shadow-lg group-focus-visible:border-primary/50 group-focus-visible:shadow-lg">
                                            <div className="relative aspect-video overflow-hidden bg-muted">
                                                {cover ? (
                                                    <img
                                                        src={cover.url}
                                                        alt={
                                                            cover.alt ||
                                                            listing.name
                                                        }
                                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-muted via-muted/70 to-secondary text-muted-foreground">
                                                        <Building2
                                                            className="size-10"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="sr-only">
                                                            {t(
                                                                'No photos available',
                                                            )}
                                                        </span>
                                                    </div>
                                                )}
                                                {cover && (
                                                    <div
                                                        className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/35 to-transparent"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </div>
                                            <CardHeader className="gap-2 px-5 pt-5">
                                                <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                                                    {listing.type_label}
                                                </p>
                                                <CardTitle className="line-clamp-2 text-xl">
                                                    {listing.name}
                                                </CardTitle>
                                                {location && (
                                                    <p className="flex items-start gap-1.5 text-sm text-muted-foreground">
                                                        <MapPin
                                                            className="mt-0.5 size-4 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        <span>{location}</span>
                                                    </p>
                                                )}
                                            </CardHeader>
                                            <CardContent className="mt-auto space-y-4 px-5 pt-0 pb-5 text-sm">
                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground">
                                                    <span>
                                                        {listing.unit_types
                                                            .length > 0
                                                            ? t(
                                                                  ':count unit types',
                                                                  {
                                                                      count: listing
                                                                          .unit_types
                                                                          .length,
                                                                  },
                                                              )
                                                            : t(
                                                                  'No unit types listed',
                                                              )}
                                                    </span>
                                                    <span aria-hidden="true">
                                                        ·
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'font-medium',
                                                            availableUnits > 0
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {availableUnits > 0
                                                            ? t(
                                                                  ':count units available',
                                                                  {
                                                                      count: availableUnits,
                                                                  },
                                                              )
                                                            : listing.unit_types
                                                                    .length > 0
                                                              ? t(
                                                                    'Currently unavailable',
                                                                )
                                                              : t(
                                                                    'No availability listed',
                                                                )}
                                                    </span>
                                                </div>
                                                <div className="flex items-end justify-between gap-3 border-t pt-4">
                                                    <p className="min-w-0 text-sm font-medium">
                                                        {startingPrice ? (
                                                            <>
                                                                <span className="text-muted-foreground">
                                                                    {t(
                                                                        'From',
                                                                    )}{' '}
                                                                </span>
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
                                                            </>
                                                        ) : (
                                                            <span className="font-normal text-muted-foreground">
                                                                {t(
                                                                    'Pricing unavailable',
                                                                )}
                                                            </span>
                                                        )}
                                                    </p>
                                                    <ArrowRight
                                                        className="size-4 shrink-0 transition-transform group-hover:translate-x-1"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                );
                            })}
                        </div>
                    ) : (
                        <Card className="border-dashed bg-muted/20">
                            <CardContent className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                                <Building2
                                    className="size-10 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h3 className="font-semibold">
                                    {search
                                        ? t('No properties match your search')
                                        : t('No properties are published yet')}
                                </h3>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    {search
                                        ? t(
                                              'Try a different search term or clear your search.',
                                          )
                                        : t(
                                              'Published properties will appear here when they are ready to welcome guests.',
                                          )}
                                </p>
                                {search && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSearch('')}
                                    >
                                        {t('Clear search')}
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </>
    );
}
