import { useForm } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Amenity, Property, UnitType } from '@/types';

type UnitTypeFormData = {
    name: string;
    description: string;
    bedrooms: string;
    bathrooms: string;
    size_sqm: string;
    furnishing: string;
    amenity_ids: number[];
};

export default function UnitTypeFormSheet({
    property,
    unitType,
    amenities,
    open,
    onOpenChange,
}: {
    property: Property;
    unitType?: UnitType | null;
    amenities: Amenity[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(unitType);
    const { data, setData, submit, reset, processing, errors } =
        useForm<UnitTypeFormData>({
            name: unitType?.name ?? '',
            description: unitType?.description ?? '',
            bedrooms:
                unitType?.bedrooms === null || unitType?.bedrooms === undefined
                    ? ''
                    : String(unitType.bedrooms),
            bathrooms: unitType?.bathrooms ?? '',
            size_sqm: unitType?.size_sqm ?? '',
            furnishing: unitType?.furnishing ?? '',
            amenity_ids:
                unitType?.amenities?.map((amenity) => amenity.id) ?? [],
        });

    const availableAmenities = [
        ...amenities,
        ...(unitType?.amenities ?? []).filter(
            (current) =>
                !amenities.some((amenity) => amenity.id === current.id),
        ),
    ];

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        submit(
            isEdit
                ? properties.unitTypes.update({
                      property: property.slug,
                      unitType: unitType!.id,
                  })
                : properties.unitTypes.store(property.slug),
            { onSuccess: () => handleOpenChange(false) },
        );
    }

    function toggleAmenity(id: number, checked: boolean | 'indeterminate') {
        if (checked === 'indeterminate') {
            return;
        }

        setData(
            'amenity_ids',
            checked
                ? [...data.amenity_ids, id]
                : data.amenity_ids.filter((current) => current !== id),
        );
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {t(isEdit ? 'Edit Unit Type' : 'New Unit Type')}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? t('Update this reusable accommodation type.')
                            : `${t('Add a reusable accommodation type to')} ${property.name}`}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="unit-type-name">{t('Name')}</Label>
                            <Input
                                id="unit-type-name"
                                required
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder={t('e.g. Studio')}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="unit-type-description">
                                {t('Description')}
                            </Label>
                            <Textarea
                                id="unit-type-description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                placeholder={t(
                                    'Describe this accommodation type',
                                )}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-bedrooms">
                                    {t('Bedrooms')}
                                </Label>
                                <Input
                                    id="unit-type-bedrooms"
                                    type="number"
                                    min={0}
                                    value={data.bedrooms}
                                    onChange={(event) =>
                                        setData('bedrooms', event.target.value)
                                    }
                                />
                                <InputError message={errors.bedrooms} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-bathrooms">
                                    {t('Bathrooms')}
                                </Label>
                                <Input
                                    id="unit-type-bathrooms"
                                    type="number"
                                    min={0}
                                    step="0.1"
                                    value={data.bathrooms}
                                    onChange={(event) =>
                                        setData('bathrooms', event.target.value)
                                    }
                                />
                                <InputError message={errors.bathrooms} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-size">
                                    {t('Size (m²)')}
                                </Label>
                                <Input
                                    id="unit-type-size"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.size_sqm}
                                    onChange={(event) =>
                                        setData('size_sqm', event.target.value)
                                    }
                                />
                                <InputError message={errors.size_sqm} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-furnishing">
                                    {t('Furnishing')}
                                </Label>
                                <Input
                                    id="unit-type-furnishing"
                                    value={data.furnishing}
                                    onChange={(event) =>
                                        setData(
                                            'furnishing',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t('e.g. Furnished')}
                                />
                                <InputError message={errors.furnishing} />
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <div>
                                <Label>{t('Amenities')}</Label>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Inactive associations stay visible until removed.',
                                    )}
                                </p>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {availableAmenities.map((amenity) => (
                                    <label
                                        key={amenity.id}
                                        className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                    >
                                        <Checkbox
                                            disabled={
                                                !amenity.is_active &&
                                                !data.amenity_ids.includes(
                                                    amenity.id,
                                                )
                                            }
                                            checked={data.amenity_ids.includes(
                                                amenity.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleAmenity(
                                                    amenity.id,
                                                    checked,
                                                )
                                            }
                                        />
                                        <span className="flex-1">
                                            {amenity.name}
                                        </span>
                                        {!amenity.is_active && (
                                            <span className="text-xs text-muted-foreground">
                                                {t('Inactive')}
                                            </span>
                                        )}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.amenity_ids} />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing}>
                            {t(isEdit ? 'Save' : 'Create')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
