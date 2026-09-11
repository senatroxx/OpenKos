import { Head, router } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import PropertyFacilitiesEditor from '@/components/features/properties/property-facilities-editor';
import { MediaGalleryManager } from '@/components/shared/media-gallery-manager';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Listing({
    property,
    amenities,
}: {
    property: Property;
    amenities: Amenity[];
}) {
    function togglePublication() {
        router.patch(properties.publication.update.url(property), {
            is_published: !property.is_published,
        });
    }

    return (
        <PropertyLayout property={property} activeTab="listing">
            <Head title={`${t('Listing')} - ${property.name}`} />

            <div className="max-w-5xl space-y-8">
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-card p-5">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Listing status')}
                            </p>
                            <Badge
                                variant={
                                    property.is_published
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {t(
                                    property.is_published
                                        ? 'Published'
                                        : 'Draft',
                                )}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {property.is_published
                                ? t(
                                      'This property is visible in the public listing.',
                                  )
                                : t(
                                      'Publish this property when its listing is ready.',
                                  )}
                        </p>
                        {property.public_slug && (
                            <p className="font-mono text-xs text-muted-foreground">
                                /{property.public_slug}
                            </p>
                        )}
                    </div>
                    <Button
                        type="button"
                        variant={property.is_published ? 'outline' : 'default'}
                        disabled={
                            property.is_active === false &&
                            !property.is_published
                        }
                        onClick={togglePublication}
                    >
                        <Globe className="size-4" />
                        {t(property.is_published ? 'Unpublish' : 'Publish')}
                    </Button>
                </section>

                <section className="space-y-4">
                    <div>
                        <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            {t('Listing content')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'The property description shown to listing visitors.',
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-card p-5">
                        {property.description ? (
                            <p className="text-sm whitespace-pre-wrap">
                                {property.description}
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('No property description yet.')}
                            </p>
                        )}
                    </div>
                </section>

                <PropertyFacilitiesEditor
                    property={property}
                    amenities={amenities}
                />

                <section className="space-y-4">
                    <div>
                        <div className="flex items-center justify-between gap-4">
                            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Property gallery')}
                            </p>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Photos used by the property public listing.')}
                        </p>
                    </div>
                    <MediaGalleryManager
                        items={property.gallery ?? []}
                        idPrefix="property-listing"
                        presentation="gallery"
                        uploadUrl={properties.gallery.store.url(property)}
                        reorderUrl={properties.gallery.reorder.url(property)}
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
                </section>
            </div>
        </PropertyLayout>
    );
}
