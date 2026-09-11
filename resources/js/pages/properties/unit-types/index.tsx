import { Head, router } from '@inertiajs/react';
import {
    EllipsisVertical,
    Globe,
    ImageOff,
    ImageIcon,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import UnitTypeFormSheet from '@/components/features/properties/unit-type-form-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property, UnitType } from '@/types';
import { PropertyLayout } from '../layout';

type PageProps = {
    property: Property;
    unitTypes: UnitType[];
    amenities: Amenity[];
};

function furnishingLabel(value: string | number | null): string | null {
    if (value === null) {
        return null;
    }

    const normalized = String(value).trim().toLowerCase();

    if (normalized === '' || normalized === '0') {
        return null;
    }

    const labels: Record<string, string> = {
        furnished: 'Furnished',
        'semi-furnished': 'Semi-furnished',
        unfurnished: 'Unfurnished',
    };

    if (labels[normalized]) {
        return t(labels[normalized]);
    }

    return String(value)
        .trim()
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function formattedNumber(value: number | string): string {
    const numericValue = Number(value);

    return Number.isFinite(numericValue) ? String(numericValue) : String(value);
}

function metricLabel(
    value: number | string | null,
    singular: string,
    plural: string,
): string | null {
    if (value === null || value === '') {
        return null;
    }

    return `${formattedNumber(value)} ${t(Number(value) === 1 ? singular : plural)}`;
}

function sizeLabel(value: string | null): string | null {
    if (value === null || value === '') {
        return null;
    }

    return `${formattedNumber(value)} ${t('m²')}`;
}

function unitCountLabel(count: number): string {
    return `${count} ${t(count === 1 ? 'unit' : 'units')}`;
}

function photoCountLabel(count: number): string {
    return `${count} ${t(count === 1 ? 'photo' : 'photos')}`;
}

export default function Index({ property, unitTypes, amenities }: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingUnitType, setEditingUnitType] = useState<UnitType | null>(
        null,
    );
    const currentEditingUnitType = editingUnitType
        ? (unitTypes.find((unitType) => unitType.id === editingUnitType.id) ??
          editingUnitType)
        : null;

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

    function togglePublication(unitType: UnitType) {
        router.patch(properties.unitTypes.publication.update.url({
            property: property.slug,
            unitType: unitType.id,
        }), {
            is_published: !unitType.is_published,
        });
    }

    return (
        <PropertyLayout property={property} activeTab="unit-types">
            <Head title={`${t('Unit Types')} - ${property.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-end">
                    <Button onClick={openCreate}>{t('New Unit Type')}</Button>
                </div>

                {unitTypes.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <p className="font-medium">{t('No Unit Types yet.')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Create a reusable type before assigning it to units.',
                            )}
                        </p>
                        <Button className="mt-4" onClick={openCreate}>
                            {t('Create your first Unit Type')}
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {unitTypes.map((unitType) => {
                            const gallery = unitType.gallery ?? [];
                            const cover =
                                gallery.find((item) => item.position === 0) ??
                                gallery[0];
                            const visibleAmenities = (
                                unitType.amenities ?? []
                            ).slice(0, 3);
                            const additionalAmenities = Math.max(
                                (unitType.amenities?.length ?? 0) -
                                    visibleAmenities.length,
                                0,
                            );
                            const details = [
                                metricLabel(
                                    unitType.bedrooms,
                                    'bedroom',
                                    'bedrooms',
                                ),
                                metricLabel(
                                    unitType.bathrooms,
                                    'bathroom',
                                    'bathrooms',
                                ),
                                sizeLabel(unitType.size_sqm),
                                furnishingLabel(unitType.furnishing),
                            ].filter(
                                (value): value is string => value !== null,
                            );

                            return (
                                <article
                                    key={unitType.id}
                                    className="flex gap-3 rounded-lg border bg-card p-3 shadow-xs sm:gap-4 sm:p-4"
                                >
                                    <div className="h-24 w-28 shrink-0 overflow-hidden rounded-md bg-muted sm:h-28 sm:w-40">
                                        {cover ? (
                                            <img
                                                src={cover.url}
                                                alt={cover.alt ?? unitType.name}
                                                loading="lazy"
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-full flex-col items-center justify-center gap-1 text-muted-foreground">
                                                <ImageOff
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                                <span className="text-xs">
                                                    {t('No photos')}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="truncate font-semibold">
                                                        {unitType.name}
                                                    </h2>
                                                    <StatusBadge
                                                        domain="property"
                                                        value={
                                                            unitType.is_active
                                                                ? 'active'
                                                                : 'inactive'
                                                        }
                                                    />
                                                    <Badge
                                                        variant={
                                                            unitType.is_published
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {t(
                                                            unitType.is_published
                                                                ? 'Published'
                                                                : 'Unpublished',
                                                        )}
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {unitCountLabel(
                                                            unitType.units_count ??
                                                                0,
                                                        )}
                                                    </Badge>
                                                </div>
                                                {unitType.description && (
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {unitType.description}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex shrink-0 items-center gap-1">
                                                <Button
                                                    onClick={() =>
                                                        openEdit(unitType)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                    {t('Edit')}
                                                </Button>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <span className="sr-only">
                                                                {t('Actions')}
                                                            </span>
                                                            <EllipsisVertical
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                togglePublication(
                                                                    unitType,
                                                                )
                                                            }
                                                        >
                                                            <Globe className="size-4" />
                                                            {t(
                                                                unitType.is_published
                                                                    ? 'Unpublish'
                                                                    : 'Publish',
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                toggleActive(
                                                                    unitType,
                                                                )
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
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>

                                        <div className="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                            {details.length > 0 ? (
                                                details.map((detail, index) => (
                                                    <span key={detail}>
                                                        {index > 0 && (
                                                            <span
                                                                className="mr-2"
                                                                aria-hidden="true"
                                                            >
                                                                ·
                                                            </span>
                                                        )}
                                                        {detail}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {t('No unit details')}
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-2 flex min-w-0 flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                            {visibleAmenities.length > 0 ? (
                                                visibleAmenities.map(
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
                                                            {!amenity.is_active &&
                                                                ` (${t('inactive')})`}
                                                        </Badge>
                                                    ),
                                                )
                                            ) : (
                                                <span className="text-xs">
                                                    {t('No amenities')}
                                                </span>
                                            )}
                                            {additionalAmenities > 0 && (
                                                <Badge variant="outline">
                                                    +{additionalAmenities}
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="mt-3 flex items-center gap-1 text-xs text-muted-foreground">
                                            <ImageIcon
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {photoCountLabel(gallery.length)}
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>

            <UnitTypeFormSheet
                key={`${currentEditingUnitType?.id ?? 'new'}-${currentEditingUnitType?.updated_at ?? ''}`}
                property={property}
                unitType={currentEditingUnitType}
                amenities={amenities}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
        </PropertyLayout>
    );
}
