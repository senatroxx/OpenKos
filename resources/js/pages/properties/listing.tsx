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
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type {
    Auth,
    ListingIssue,
    ListingPageProps,
} from '@/types';
import { PropertyLayout } from './layout';

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

function SummaryMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between gap-3 px-4 py-3 sm:block sm:space-y-1">
            <p className="text-sm text-muted-foreground">{t(label)}</p>
            <p className="text-lg font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export default function Listing({ property, amenities, readiness }: ListingPageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [photosOpen, setPhotosOpen] = useState(false);
    const canManage = auth.permissions.includes('properties.update');
    const publishedButNotVisible =
        readiness.is_published && !readiness.is_publicly_visible;
    const gallery = property.gallery ?? [];
    const coverPhoto = gallery[0];
    const rentalMode = property.rental_mode ?? 'unit';

    const unitTypeCount = readiness.unit_types.length;
    const listedUnitTypeCount = readiness.unit_types.filter(
        (unitType) => unitType.is_included,
    ).length;
    const needsAttentionUnitTypeCount = readiness.unit_types.filter(
        (unitType) =>
            unitType.status === 'blocked' || unitType.status === 'inactive',
    ).length;
    const notListedUnitTypeCount = unitTypeCount - listedUnitTypeCount;

    function renderUnitTypeSummary() {
        if (unitTypeCount === 0) {
            return (
                <div className="space-y-3 rounded-lg border border-dashed p-4">
                    <p className="text-sm text-muted-foreground">
                        {t('No Unit Types configured.')}
                    </p>
                    <Button asChild variant="outline" size="sm">
                        <Link href={properties.unitTypes.index.url(property)}>
                            {t('Set up Unit Types')}
                        </Link>
                    </Button>
                </div>
            );
        }

        return (
            <div className="space-y-4 rounded-lg border bg-card p-4 sm:p-5">
                <p className="text-base font-medium">
                    {unitTypeCount}{' '}
                    {t(unitTypeCount === 1 ? 'Unit Type' : 'Unit Types')}
                </p>
                <div className="grid grid-cols-1 divide-y rounded-md border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <SummaryMetric label="Listed" value={listedUnitTypeCount} />
                    <SummaryMetric
                        label="Not listed"
                        value={notListedUnitTypeCount}
                    />
                    <SummaryMetric
                        label="Needs attention"
                        value={needsAttentionUnitTypeCount}
                    />
                </div>
                {needsAttentionUnitTypeCount > 0 && (
                    <p className="text-sm text-amber-700 dark:text-amber-300">
                        {t(
                            ':attention of :total Unit Types need attention before they can be listed.',
                            {
                                attention: needsAttentionUnitTypeCount,
                                total: unitTypeCount,
                            },
                        )}
                    </p>
                )}
                <Button asChild variant="outline" size="sm">
                    <Link href={properties.unitTypes.index.url(property)}>
                        {t('View all Unit Types')}
                        <span aria-hidden="true">→</span>
                    </Link>
                </Button>
            </div>
        );
    }

    function renderWholePropertySummary() {
        const wholeProperty = readiness.whole_property;

        if (!wholeProperty) {
            return null;
        }

        const ready = wholeProperty.has_active_pricing;

        return (
            <div className="space-y-4 rounded-lg border bg-card p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="font-medium">{t('Entire property')}</p>
                        <p className="text-sm text-muted-foreground">
                            {t('Whole-property rental offering')}
                        </p>
                    </div>
                    <Badge
                        variant={
                            wholeProperty.is_listed ? 'default' : 'outline'
                        }
                    >
                        {t(wholeProperty.is_listed ? 'Listed' : 'Not listed')}
                    </Badge>
                </div>
                <div>
                    <p className="font-medium">
                        {t(ready ? 'Ready' : 'Needs attention')}
                    </p>
                    {!ready && wholeProperty.reason && (
                        <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            {t(wholeProperty.reason)}
                        </p>
                    )}
                </div>
                {wholeProperty.action && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={wholeProperty.action.url}>
                            {t(wholeProperty.action.label)}
                        </Link>
                    </Button>
                )}
            </div>
        );
    }

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
                                    !readiness.can_publish
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
                                rentalMode === 'unit'
                                    ? 'Unit Types available for your public listing.'
                                    : rentalMode === 'hybrid'
                                      ? 'Entire property and Unit Types available for your public listing.'
                                      : 'Entire property offering available for your public listing.',
                            )}
                        </p>
                    </div>
                    {rentalMode === 'whole_property' ? (
                        renderWholePropertySummary()
                    ) : rentalMode === 'hybrid' ? (
                        <div className="space-y-4">
                            {renderWholePropertySummary()}
                            {renderUnitTypeSummary()}
                        </div>
                    ) : (
                        renderUnitTypeSummary()
                    )}
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
