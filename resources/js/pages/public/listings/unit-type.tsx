import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import PublicApplicationForm from '@/components/features/tenant-portal/public-application-form';
import { PublicPricingOptions } from '@/components/shared';
import PublicListingGallery from '@/components/shared/public-listing-gallery';
import PublicListingHead from '@/components/shared/public-listing-head';
import { AmenityIcon } from '@/lib/amenity-icons';
import { formatBillingOptionLabel, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { show as applicationShow } from '@/routes/applications';
import { edit as profileEdit } from '@/routes/portal/profile';
import { show as propertyShow } from '@/routes/public/portal';
import type {
    PublicPricingOptionsProps,
    PublicRentalSummaryProps,
    PublicApplicationFormTarget,
    PublicUnitTypeAttributesProps,
    PublicListingPageAuthProps,
    PublicPortalUnitTypePageProps,
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
        <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
            {attributes.map((attribute) => (
                <span
                    key={attribute}
                    className="rounded-full border bg-muted/40 px-3 py-1 capitalize"
                >
                    {attribute}
                </span>
            ))}
        </div>
    );
}

function PricingOptions({ prices }: PublicPricingOptionsProps) {
    return <PublicPricingOptions prices={prices} />;
}

function RentalSummary({
    unitType,
    existingApplication,
    applyHref,
    applyLabel,
    onApply,
    applicationForm,
}: PublicRentalSummaryProps) {
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
                                {formatBillingOptionLabel(
                                    startingPrice.billing_interval,
                                    startingPrice.billing_unit,
                                )}
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

                {existingApplication ? (
                    <Link
                        href={applicationShow(existingApplication.id)}
                        className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {t('View application')}
                    </Link>
                ) : applyHref ? (
                    <Link
                        href={applyHref}
                        onClick={(event) => {
                            if (applyHref.includes('#application-form')) {
                                event.preventDefault();
                                onApply();
                            }
                        }}
                        className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {applyLabel}
                    </Link>
                ) : null}
                {applicationForm}
            </div>
        </aside>
    );
}

export default function UnitType({
    listing,
    canonicalUrl,
    open_application: existingApplication,
    metadata,
}: PublicPortalUnitTypePageProps) {
    const { auth } = usePage<PublicListingPageAuthProps>().props;
    const [applicationFormVisible, setApplicationFormVisible] = useState(false);
    const unitType = listing.unit_type;
    const title =
        unitType.bedrooms !== null &&
        !/^\d+(?:\.\d+)?\s*BD\b/i.test(unitType.name)
            ? `${displayNumber(unitType.bedrooms)}BD ${unitType.name}`
            : unitType.name;
    const applicationTarget: PublicApplicationFormTarget = {
        target_type: 'unit_type',
        property_slug: listing.property.slug,
        property_name: listing.property.name,
        unit_type_slug: unitType.slug,
        unit_type_name: unitType.name,
        rental_options: unitType.starting_prices,
    };
    const applicationReturnUrl = `${canonicalUrl}#application-form`;

    useEffect(() => {
        if (applicationFormVisible) {
            document.getElementById('application-form')?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [applicationFormVisible]);

    return (
        <>
            <PublicListingHead
                metadata={metadata}
            />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <nav aria-label="Breadcrumb" className="text-sm">
                    <Link
                        href={propertyShow({ property: listing.property.slug })}
                        className="inline-flex min-h-11 items-center gap-2 text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        {listing.property.name}
                    </Link>
                </nav>

                <header>
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                        {title}
                    </h1>
                </header>

                <PublicListingGallery items={unitType.gallery} />
                <UnitTypeAttributes unitType={unitType} />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-6">
                    <div className="flex min-w-0 flex-col gap-6">
                        {unitType.description && (
                            <section
                                aria-labelledby="about-heading"
                                className="space-y-2"
                            >
                                <h2
                                    id="about-heading"
                                    className="text-xl font-semibold"
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
                                className="space-y-3 rounded-xl border bg-card p-4 sm:p-5"
                            >
                                <h2
                                    id="amenities-heading"
                                    className="text-xl font-semibold"
                                >
                                    {t('Features and amenities')}
                                </h2>
                                <ul className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                                    {unitType.amenities.map((amenity) => (
                                        <li
                                            key={amenity.name}
                                            className="flex items-center gap-3 border-b border-border/60 py-2.5 text-sm"
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
                            className="space-y-3 rounded-xl border bg-card p-4 sm:p-5"
                        >
                            <h2
                                id="pricing-heading"
                                className="text-xl font-semibold"
                            >
                                {t('Pricing options')}
                            </h2>
                            <PricingOptions prices={unitType.starting_prices} />
                        </section>
                    </div>

                    <RentalSummary
                        unitType={unitType}
                        existingApplication={existingApplication}
                        applyHref={!auth?.user
                            ? login.url({ query: { redirect: applicationReturnUrl } })
                            : auth.user.phone
                              ? '#application-form'
                              : profileEdit.url({ query: { return: applicationReturnUrl } })}
                        applyLabel={auth?.user?.phone ? t('Apply for this unit type') : t('Complete your profile to apply')}
                        onApply={() => setApplicationFormVisible(true)}
                        applicationForm={
                            auth?.user && applicationFormVisible ? (
                                <PublicApplicationForm
                                    target={applicationTarget}
                                    existingApplication={existingApplication}
                                />
                            ) : undefined
                        }
                    />
                </div>
            </div>
        </>
    );
}
