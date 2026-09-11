import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
    const [deleteConfirm, setDeleteConfirm] = useState<Amenity | null>(null);
    const facilityForm = useForm<{ amenity_ids: number[] }>({
        amenity_ids: property.facilities?.map((amenity) => amenity.id) ?? [],
    });
    const customForm = useForm<{ name: string; scope: 'property' }>({
        name: '',
        scope: 'property',
    });
    const assignedAmenities = property.facilities ?? [];

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

    function createCustomAmenity(event: React.FormEvent) {
        event.preventDefault();
        customForm.post(properties.amenities.store.url(property), {
            onSuccess: () => customForm.reset(),
        });
    }

    function confirmDelete() {
        if (!deleteConfirm) {
            return;
        }

        router.delete(
            properties.amenities.destroy.url({
                property: property.slug,
                amenity: deleteConfirm.id,
            }),
            {
                onSuccess: () => {
                    facilityForm.setData(
                        'amenity_ids',
                        facilityForm.data.amenity_ids.filter(
                            (id) => id !== deleteConfirm.id,
                        ),
                    );
                    setDeleteConfirm(null);
                },
            },
        );
    }

    function handleManageChange(open: boolean) {
        setManageOpen(open);

        if (!open) {
            facilityForm.reset();
            facilityForm.clearErrors();
            customForm.reset();
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
                            {amenity.name}
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
                                'Choose the amenities shown on this property listing.',
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-6">
                        <form
                            id="property-facilities-form"
                            onSubmit={saveFacilities}
                            className="space-y-6"
                        >
                            <div className="grid gap-3">
                                {amenities.map((amenity) => (
                                    <div
                                        key={amenity.id}
                                        className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                    >
                                        <label
                                            htmlFor={`property-facility-${amenity.id}`}
                                            className="flex min-w-0 flex-1 items-center gap-3"
                                        >
                                            <Checkbox
                                                id={`property-facility-${amenity.id}`}
                                                disabled={
                                                    amenity.scope ===
                                                        'unit_type' ||
                                                    (!amenity.is_active &&
                                                        !facilityForm.data.amenity_ids.includes(
                                                            amenity.id,
                                                        ))
                                                }
                                                checked={facilityForm.data.amenity_ids.includes(
                                                    amenity.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleAmenity(
                                                        amenity.id,
                                                        checked,
                                                    )
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                {amenity.name}
                                            </span>
                                            {amenity.scope === 'unit_type' && (
                                                <span className="text-xs text-muted-foreground">
                                                    {t('Unit Type only')}
                                                </span>
                                            )}
                                            {!amenity.is_active && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    {t('Inactive')}
                                                </Badge>
                                            )}
                                        </label>
                                        {amenity.owner_property_id ===
                                            property.id && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 shrink-0 text-destructive hover:text-destructive"
                                                aria-label={t(
                                                    'Delete custom amenity',
                                                )}
                                                onClick={() =>
                                                    setDeleteConfirm(amenity)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <InputError
                                message={facilityForm.errors.amenity_ids}
                            />
                        </form>

                        <form
                            onSubmit={createCustomAmenity}
                            className="mt-6 grid gap-3 rounded-lg border bg-muted/20 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="custom-amenity">
                                    {t('Add custom amenity')}
                                </Label>
                                <Input
                                    id="custom-amenity"
                                    value={customForm.data.name}
                                    onChange={(event) =>
                                        customForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t('e.g. Rooftop garden')}
                                />
                                <InputError message={customForm.errors.name} />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={
                                    customForm.processing ||
                                    !customForm.data.name.trim()
                                }
                            >
                                {t('Add amenity')}
                            </Button>
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
                            {t('Save facilities')}
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <Dialog
                open={deleteConfirm !== null}
                onOpenChange={() => setDeleteConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete custom amenity?')}</DialogTitle>
                        <DialogDescription>
                            {t('Delete')}{' '}
                            <span className="font-medium">
                                {deleteConfirm?.name}
                            </span>{' '}
                            {t(
                                'permanently? It will be removed from this property and any Unit Types using it. This cannot be undone.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {t('Delete amenity')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
