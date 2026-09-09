import { Head, router } from '@inertiajs/react';
import { Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import UnitTypeFormSheet from '@/components/features/properties/unit-type-form-sheet';
import { MediaGalleryManager } from '@/components/shared/media-gallery-manager';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property, UnitType } from '@/types';
import { PropertyLayout } from '../layout';

type PageProps = {
    property: Property;
    unitTypes: UnitType[];
    amenities: Amenity[];
};

export default function Index({ property, unitTypes, amenities }: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingUnitType, setEditingUnitType] = useState<UnitType | null>(
        null,
    );

    function openCreate() {
        setEditingUnitType(null);
        setFormOpen(true);
    }

    function openEdit(unitType: UnitType) {
        setEditingUnitType(unitType);
        setFormOpen(true);
    }

    function toggleActive(unitType: UnitType) {
        router.post(
            unitType.is_active
                ? properties.unitTypes.deactivate.url({
                      property: property.slug,
                      unitType: unitType.id,
                  })
                : properties.unitTypes.restore.url({
                      property: property.slug,
                      unitType: unitType.id,
                  }),
        );
    }

    return (
        <PropertyLayout property={property} activeTab="unit-types">
            <Head title={`${t('UnitTypes')} - ${property.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-end">
                    <Button onClick={openCreate}>{t('New UnitType')}</Button>
                </div>

                {unitTypes.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <p className="font-medium">{t('No UnitTypes yet.')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Create a reusable type before assigning it to units.',
                            )}
                        </p>
                        <Button className="mt-4" onClick={openCreate}>
                            {t('Create your first UnitType')}
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {unitTypes.map((unitType) => (
                            <section
                                key={unitType.id}
                                className="space-y-4 rounded-lg border bg-card p-6 shadow-xs"
                            >
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-semibold">
                                                {unitType.name}
                                            </h2>
                                            <StatusBadge
                                                domain="property"
                                                value={
                                                    unitType.is_active
                                                        ? 'active'
                                                        : 'archived'
                                                }
                                            />
                                            <Badge variant="outline">
                                                {unitType.units_count ?? 0}{' '}
                                                {t('units')}
                                            </Badge>
                                        </div>
                                        {unitType.description && (
                                            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                                                {unitType.description}
                                            </p>
                                        )}
                                        <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Bedrooms')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {unitType.bedrooms ?? '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Bathrooms')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {unitType.bathrooms ?? '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Size')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {unitType.size_sqm
                                                        ? `${unitType.size_sqm} m²`
                                                        : '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Furnishing')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {unitType.furnishing ?? '—'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => openEdit(unitType)}
                                        >
                                            <Pencil className="size-4" />
                                            {t('Edit')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                toggleActive(unitType)
                                            }
                                        >
                                            {unitType.is_active ? (
                                                <Trash2 className="size-4" />
                                            ) : (
                                                <RotateCcw className="size-4" />
                                            )}
                                            {t(
                                                unitType.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                        </Button>
                                    </div>
                                </div>

                                {unitType.amenities &&
                                    unitType.amenities.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {unitType.amenities.map(
                                                (amenity) => (
                                                    <Badge
                                                        key={amenity.id}
                                                        variant={
                                                            amenity.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {amenity.name}
                                                        {!amenity.is_active
                                                            ? ` (${t('inactive')})`
                                                            : ''}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    )}

                                <MediaGalleryManager
                                    items={unitType.gallery ?? []}
                                    idPrefix={`unit-type-${unitType.id}`}
                                    uploadUrl={properties.unitTypes.gallery.store.url(
                                        {
                                            property: property.slug,
                                            unitType: unitType.id,
                                        },
                                    )}
                                    reorderUrl={properties.unitTypes.gallery.reorder.url(
                                        {
                                            property: property.slug,
                                            unitType: unitType.id,
                                        },
                                    )}
                                    updateUrl={(mediaId) =>
                                        properties.unitTypes.gallery.update.url(
                                            {
                                                property: property.slug,
                                                unitType: unitType.id,
                                                media: mediaId,
                                            },
                                        )
                                    }
                                    destroyUrl={(mediaId) =>
                                        properties.unitTypes.gallery.destroy.url(
                                            {
                                                property: property.slug,
                                                unitType: unitType.id,
                                                media: mediaId,
                                            },
                                        )
                                    }
                                />
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <UnitTypeFormSheet
                key={`${editingUnitType?.id ?? 'new'}-${editingUnitType?.updated_at ?? ''}`}
                property={property}
                unitType={editingUnitType}
                amenities={amenities}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
        </PropertyLayout>
    );
}
