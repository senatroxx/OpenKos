import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { AmenityIcon } from '@/lib/amenity-icons';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property } from '@/types';

export default function PropertyFacilitiesEditor({
    property,
    amenities,
}: {
    property: Property;
    amenities: Amenity[];
}) {
    const [manageOpen, setManageOpen] = useState(false);
    const facilityForm = useForm<{ amenity_ids: number[] }>({
        amenity_ids: property.facilities?.map((amenity) => amenity.id) ?? [],
    });
    const assignedAmenities = property.facilities ?? [];
    const assignedIds = new Set(assignedAmenities.map((amenity) => amenity.id));
    const availableAmenities = amenities
        .filter(
            (amenity) =>
                amenity.scope !== 'unit_type' || assignedIds.has(amenity.id),
        )
        .sort((left, right) => left.name.localeCompare(right.name));

    function toggleAmenity(id: number, checked: boolean | 'indeterminate') {
        if (checked === 'indeterminate') {
            return;
        }

        facilityForm.setData(
            'amenity_ids',
            checked
                ? [...facilityForm.data.amenity_ids, id]
                : facilityForm.data.amenity_ids.filter(
                      (current) => current !== id,
                  ),
        );
    }

    function saveFacilities(event: React.FormEvent) {
        event.preventDefault();
        facilityForm.put(properties.facilities.update.url(property), {
            onSuccess: () => setManageOpen(false),
        });
    }

    function handleManageChange(open: boolean) {
        setManageOpen(open);

        if (!open) {
            facilityForm.reset();
            facilityForm.clearErrors();
        }
    }

    return (
        <section className="space-y-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Amenities')}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('Amenities shown on the property listing.')}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setManageOpen(true)}
                >
                    {t('Manage')}
                </Button>
            </div>

            {assignedAmenities.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {assignedAmenities.map((amenity) => (
                        <Badge
                            key={amenity.id}
                            variant="outline"
                            className={
                                amenity.is_active
                                    ? undefined
                                    : 'text-muted-foreground'
                            }
                        >
                            <span className="flex items-center gap-1.5">
                                <AmenityIcon
                                    icon={amenity.icon}
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {amenity.name}
                            </span>
                            {!amenity.is_active && ` · ${t('Inactive')}`}
                        </Badge>
                    ))}
                </div>
            ) : (
                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    {t('No amenities assigned yet.')}
                </div>
            )}

            <Sheet open={manageOpen} onOpenChange={handleManageChange}>
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('Manage amenities')}</SheetTitle>
                        <SheetDescription>
                            {t(
                                'Choose the amenities shown on this property listing. Catalog changes are managed in Settings.',
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-6">
                        <form
                            id="property-facilities-form"
                            onSubmit={saveFacilities}
                            className="space-y-3"
                        >
                            {availableAmenities.length > 0 ? (
                                availableAmenities.map((amenity) => {
                                    const selected =
                                        facilityForm.data.amenity_ids.includes(
                                            amenity.id,
                                        );

                                    return (
                                        <label
                                            key={amenity.id}
                                            htmlFor={`property-facility-${amenity.id}`}
                                            className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                        >
                                            <Checkbox
                                                id={`property-facility-${amenity.id}`}
                                                disabled={
                                                    !amenity.is_active &&
                                                    !selected
                                                }
                                                checked={selected}
                                                onCheckedChange={(checked) =>
                                                    toggleAmenity(
                                                        amenity.id,
                                                        checked,
                                                    )
                                                }
                                            />
                                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                                <AmenityIcon
                                                    icon={amenity.icon}
                                                    className="size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span className="truncate">
                                                    {amenity.name}
                                                </span>
                                            </span>
                                            {!amenity.is_active && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    {t('Inactive')}
                                                </Badge>
                                            )}
                                        </label>
                                    );
                                })
                            ) : (
                                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    {t(
                                        'No active property amenities are available. Add one in Settings.',
                                    )}
                                </p>
                            )}
                            <InputError
                                message={facilityForm.errors.amenity_ids}
                            />
                        </form>
                    </div>

                    <SheetFooter className="border-t bg-background/95 sm:flex-row sm:justify-end">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => setManageOpen(false)}
                            disabled={facilityForm.processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            form="property-facilities-form"
                            type="submit"
                            disabled={facilityForm.processing}
                        >
                            {t('Save amenities')}
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </section>
    );
}
