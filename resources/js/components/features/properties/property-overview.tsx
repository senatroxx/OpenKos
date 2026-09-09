import { MediaGalleryManager } from '@/components/shared/media-gallery-manager';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property } from '@/types';
import PropertyFacilitiesEditor from './property-facilities-editor';

export default function PropertyOverview({
    property,
    amenities,
}: {
    property: Property;
    amenities: Amenity[];
}) {
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;
    const locationLabel = [
        city?.name,
        property?.region?.name,
        property?.postal_code,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <div className="space-y-8">
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">
                    {t('Status:')}
                </span>
                <StatusBadge
                    domain="property"
                    value={property.is_active ? 'active' : 'archived'}
                />
                {property.type && (
                    <Badge variant="outline">
                        {property.type_label ?? property.type}
                    </Badge>
                )}
            </div>

            {property.address && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Address')}
                    </p>
                    <p className="mt-1 text-sm">{property.address}</p>
                    {locationLabel && (
                        <p className="text-sm text-muted-foreground">
                            {locationLabel}
                        </p>
                    )}
                </div>
            )}

            {!property.address && city && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('City')}
                    </p>
                    <p className="mt-1 text-sm">{city.name}</p>
                </div>
            )}

            {property.phone && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Phone')}
                    </p>
                    <p className="mt-1 text-sm">{property.phone}</p>
                </div>
            )}

            {property.description && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Description')}
                    </p>
                    <p className="mt-1 max-w-3xl text-sm whitespace-pre-wrap">
                        {property.description}
                    </p>
                </div>
            )}

            <div>
                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    {t('Statistics')}
                </p>
                <div className="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.units_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Total Units')}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.occupied_units_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Occupied')}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.tenants_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Tenants')}
                        </p>
                    </div>
                </div>
            </div>

            <PropertyFacilitiesEditor
                property={property}
                amenities={amenities}
            />

            <section className="space-y-4">
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Property gallery')}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Manage ordered property photos and their metadata.',
                        )}
                    </p>
                </div>
                <MediaGalleryManager
                    items={property.gallery ?? []}
                    idPrefix="property"
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
    );
}
