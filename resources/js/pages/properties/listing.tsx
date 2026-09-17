import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleAlert,
    ExternalLink,
    Globe,
    ImageIcon,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import PropertyFacilitiesEditor from '@/components/features/properties/property-facilities-editor';
import PropertyFormSheet from '@/components/features/properties/property-form-sheet';
import { MediaGalleryManager } from '@/components/shared/media-gallery-manager';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type {
    Amenity,
    Auth,
    ListingIssue,
    ListingReadiness,
    ListingUnitType,
    Property,
} from '@/types';
import { PropertyLayout } from './layout';

type PageProps = {
    property: Property;
    amenities: Amenity[];
    readiness: ListingReadiness;
};

function unitCountLabel(
    count: number,
    singular: string,
    plural = singular + 's',
): string {
    return String(count) + ' ' + t(count === 1 ? singular : plural);
}

function issueAction(issue: ListingIssue) {
    if (!issue.action) {
        return null;
    }

    return (
        <Button asChild variant="link" size="sm" className="h-auto px-0">
            <Link href={issue.action.url}>{t(issue.action.label)}</Link>
        </Button>
    );
}

function IssueList({
    issues,
    tone,
}: {
    issues: ListingIssue[];
    tone: 'blocking' | 'recommendation';
}) {
    const isBlocking = tone === 'blocking';

    return (
        <div className="divide-y rounded-md border">
            {issues.map((issue) => (
                <div key={issue.key} className="flex items-start gap-3 p-3">
                    {isBlocking ? (
                        <CircleAlert
                            className="mt-0.5 size-4 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                    ) : (
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            aria-hidden="true"
                        />
                    )}
                    <div className="min-w-0 flex-1">
                        <p className="text-sm">{t(issue.message)}</p>
                        {issueAction(issue)}
                    </div>
                </div>
            ))}
        </div>
    );
}

function unitTypeVisibility(unitType: ListingUnitType): {
    label: string;
    variant: 'default' | 'outline' | 'destructive';
} {
    if (!unitType.is_included) {
        return { label: 'Not listed', variant: 'outline' };
    }

    if (unitType.status === 'ready') {
        return { label: 'Listed', variant: 'default' };
    }

    return { label: 'Listed · Needs attention', variant: 'destructive' };
}

export default function Listing({ property, amenities, readiness }: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [photosOpen, setPhotosOpen] = useState(false);
    const canManage = auth.permissions.includes('properties.update');
    const publishedButNotVisible =
        readiness.is_published && !readiness.is_publicly_visible;
    const gallery = property.gallery ?? [];
    const coverPhoto = gallery[0];
    const rentalMode = property.rental_mode ?? 'unit';

    const rentalOptions = [
        ...(readiness.whole_property
            ? [
                  {
                      key: 'whole-property',
                      name: t('Entire property'),
                      visibility: {
                          label: readiness.whole_property.is_listed
                              ? 'Listed'
                              : 'Not listed',
                          variant: readiness.whole_property.is_listed
                              ? ('default' as const)
                              : ('outline' as const),
                      },
                      inventory: null,
                      status: readiness.whole_property.has_active_pricing
                          ? 'Ready'
                          : 'Needs attention',
                      price: readiness.whole_property.starting_price,
                      reason: readiness.whole_property.reason,
                      href: null,
                  },
              ]
            : []),
        ...(rentalMode !== 'whole_property'
            ? readiness.unit_types.map((unitType) => ({
                  key: String(unitType.id),
                  name: unitType.name,
                  visibility: unitTypeVisibility(unitType),
                  inventory: {
                      physical: unitType.physical_units,
                      available: unitType.available_units,
                  },
                  status:
                      unitType.status === 'ready' ? 'Ready' : 'Needs attention',
                  price: unitType.starting_price,
                  reason: unitType.reason,
                  href:
                      properties.unitTypes.index.url(property) +
                      '#unit-type-' +
                      unitType.id,
              }))
            : []),
    ];

    const rentalColumns: TableColumn<(typeof rentalOptions)[number]>[] = [
        {
            key: 'name',
            label: 'Rental option',
            className: 'align-top font-medium',
        },
        {
            key: 'visibility',
            label: 'Listing state',
            className: 'align-top',
            render: (option) => (
                <Badge variant={option.visibility.variant}>
                    {t(option.visibility.label)}
                </Badge>
            ),
        },
        {
            key: 'inventory',
            label: 'Inventory',
            className: 'align-top whitespace-nowrap',
            render: (option) =>
                option.inventory ? (
                    <>
                        <p>
                            {unitCountLabel(option.inventory.physical, 'unit')}
                        </p>
                        <p className="text-muted-foreground">
                            {unitCountLabel(
                                option.inventory.available,
                                'available',
                                'available',
                            )}
                        </p>
                    </>
                ) : (
                    <span className="text-muted-foreground">
                        {t('Entire property')}
                    </span>
                ),
        },
        {
            key: 'price',
            label: 'Starting price',
            className: 'align-top whitespace-nowrap',
            render: (option) =>
                option.price ? (
                    <>
                        <p>
                            {formatPrice(
                                option.price.amount,
                                option.price.currency,
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {option.price.billing_label}
                        </p>
                    </>
                ) : (
                    <span className="text-muted-foreground">
                        {t('Pricing missing')}
                    </span>
                ),
        },
        {
            key: 'status',
            label: 'Status',
            className: 'min-w-48 max-w-xs align-top',
            render: (option) => (
                <>
                    <p className="font-medium">{t(option.status)}</p>
                    {option.reason && (
                        <p className="mt-1 text-amber-700 dark:text-amber-300">
                            {t(option.reason)}
                        </p>
                    )}
                </>
            ),
        },
    ];

    function togglePublication() {
        router.patch(properties.publication.update.url(property), {
            is_published: !property.is_published,
        });
    }

    return (
        <PropertyLayout property={property} activeTab="listing">
            <Head title={t('Listing') + ' - ' + property.name} />

            <div className="w-full space-y-8">
                <section className="space-y-5 rounded-lg border bg-card p-5 sm:p-6">
                    <div className="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-lg font-semibold">
                                    {t('Public listing')}
                                </h1>
                                <Badge
                                    variant={
                                        readiness.is_published
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {t(
                                        readiness.is_published
                                            ? 'Published'
                                            : 'Unpublished',
                                    )}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {publishedButNotVisible
                                    ? t(
                                          'Published, but currently not visible to customers.',
                                      )
                                    : readiness.is_publicly_visible
                                      ? t(
                                            'Your listing is live and visible to customers.',
                                        )
                                      : t('Your listing is not live yet.')}
                            </p>
                            {readiness.public_url && (
                                <Button
                                    asChild
                                    variant="link"
                                    size="sm"
                                    className="h-auto px-0"
                                >
                                    <a
                                        href={readiness.public_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {t('View listing')}
                                        <ExternalLink
                                            className="ml-1 size-3.5"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </Button>
                            )}
                        </div>
                        {canManage && (
                            <Button
                                type="button"
                                variant={
                                    readiness.is_published
                                        ? 'outline'
                                        : 'default'
                                }
                                disabled={
                                    !readiness.is_published &&
                                    (!readiness.can_publish ||
                                        property.is_active === false)
                                }
                                onClick={togglePublication}
                            >
                                <Globe className="size-4" />
                                {t(
                                    readiness.is_published
                                        ? 'Unpublish'
                                        : 'Publish',
                                )}
                            </Button>
                        )}
                    </div>

                    {readiness.blockers.length > 0 && (
                        <div className="space-y-2">
                            <h2 className="text-sm font-semibold">
                                {t(
                                    readiness.is_published
                                        ? 'Needs attention to restore public visibility'
                                        : 'Needs attention to publish',
                                )}
                            </h2>
                            <IssueList
                                issues={readiness.blockers}
                                tone="blocking"
                            />
                        </div>
                    )}

                    {readiness.recommendations.length > 0 && (
                        <div className="space-y-2">
                            <h2 className="text-sm font-semibold">
                                {readiness.recommendations.length}{' '}
                                {t(
                                    readiness.recommendations.length === 1
                                        ? 'recommendation'
                                        : 'recommendations',
                                )}
                            </h2>
                            <IssueList
                                issues={readiness.recommendations}
                                tone="recommendation"
                            />
                        </div>
                    )}

                    {readiness.blockers.length === 0 &&
                        readiness.recommendations.length === 0 && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CheckCircle2
                                    className="size-4 text-emerald-600 dark:text-emerald-400"
                                    aria-hidden="true"
                                />
                                {t('No action needed.')}
                            </div>
                        )}
                </section>

                <section id="listing-details" className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('Listing details')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Content customers see on the public listing.')}
                        </p>
                    </div>

                    <div className="divide-y rounded-lg border bg-card">
                        <div
                            id="listing-description"
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <h3 className="text-sm font-medium">
                                    {t('Description')}
                                </h3>
                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                    {property.description ||
                                        t('No description yet.')}
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() => setDetailsOpen(true)}
                                >
                                    {t('Edit')}
                                </Button>
                            )}
                        </div>

                        <div id="listing-amenities" className="p-4">
                            <PropertyFacilitiesEditor
                                property={property}
                                amenities={amenities}
                                compact
                                canManage={canManage}
                            />
                        </div>

                        <div
                            id="listing-gallery"
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                {coverPhoto ? (
                                    <img
                                        src={coverPhoto.url}
                                        alt={coverPhoto.alt ?? ''}
                                        className="size-16 shrink-0 rounded-md object-cover"
                                    />
                                ) : (
                                    <div className="flex size-16 shrink-0 items-center justify-center rounded-md border border-dashed text-muted-foreground">
                                        <ImageIcon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                                <div className="min-w-0 space-y-1">
                                    <h3 className="text-sm font-medium">
                                        {t('Photos')}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {gallery.length}{' '}
                                        {t(
                                            gallery.length === 1
                                                ? 'photo'
                                                : 'photos',
                                        )}
                                    </p>
                                </div>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() => setPhotosOpen(true)}
                                >
                                    {t('Manage photos')}
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                <section
                    aria-labelledby="rental-options-heading"
                    className="min-w-0 space-y-4"
                >
                    <div>
                        <h2
                            id="rental-options-heading"
                            className="text-lg font-semibold"
                        >
                            {t('Rental options')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'These are the rental options currently configured for your public listing.',
                            )}
                        </p>
                    </div>
                    <DataTable
                        columns={rentalColumns}
                        rows={rentalOptions}
                        rowKey={(option) => option.key}
                        isRowInteractive={(option) => option.href !== null}
                        onRowClick={(option) => {
                            if (option.href) {
                                router.visit(option.href);
                            }
                        }}
                        empty={{
                            message: t(
                                'No rental options have been configured yet.',
                            ),
                            ...(canManage
                                ? {
                                      createLabel: t('Manage Unit Types'),
                                      onCreate: () =>
                                          router.visit(
                                              properties.unitTypes.index.url(
                                                  property,
                                              ),
                                          ),
                                  }
                                : {}),
                        }}
                    />
                </section>
            </div>

            {canManage && (
                <>
                    <PropertyFormSheet
                        property={property}
                        open={detailsOpen}
                        onOpenChange={setDetailsOpen}
                    />

                    <Sheet open={photosOpen} onOpenChange={setPhotosOpen}>
                        <SheetContent className="w-full sm:max-w-3xl">
                            <SheetHeader>
                                <SheetTitle>{t('Manage photos')}</SheetTitle>
                                <SheetDescription>
                                    {t(
                                        'Add, reorder, and update the photos shown on this property listing.',
                                    )}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-6">
                                <MediaGalleryManager
                                    items={gallery}
                                    idPrefix="property-listing"
                                    presentation="inline"
                                    uploadUrl={properties.gallery.store.url(
                                        property,
                                    )}
                                    reorderUrl={properties.gallery.reorder.url(
                                        property,
                                    )}
                                    updateUrl={(mediaId) =>
                                        properties.gallery.update.url({
                                            property,
                                            media: mediaId,
                                        })
                                    }
                                    destroyUrl={(mediaId) =>
                                        properties.gallery.destroy.url({
                                            property,
                                            media: mediaId,
                                        })
                                    }
                                />
                            </div>
                        </SheetContent>
                    </Sheet>
                </>
            )}
        </PropertyLayout>
    );
}
