import { useForm } from '@inertiajs/react';
import { ChevronsUpDown, Plus } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { MediaGalleryManager } from '@/components/shared/media-gallery-manager';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
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

type CustomAmenityFormData = {
    name: string;
    scope: 'unit_type';
};

const furnishingOptions = [
    { value: 'unfurnished', label: 'Unfurnished' },
    { value: 'semi-furnished', label: 'Semi-furnished' },
    { value: 'furnished', label: 'Furnished' },
] as const;

function normalizeFurnishing(value: string | null | undefined): string {
    const normalized = value?.trim().toLowerCase() ?? '';

    return (
        furnishingOptions.find(
            (option) =>
                option.value === normalized ||
                option.label.toLowerCase() === normalized,
        )?.value ?? ''
    );
}

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
    const [amenityPickerOpen, setAmenityPickerOpen] = useState(false);
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
            furnishing: normalizeFurnishing(unitType?.furnishing),
            amenity_ids:
                unitType?.amenities?.map((amenity) => amenity.id) ?? [],
        });
    const customForm = useForm<CustomAmenityFormData>({
        name: '',
        scope: 'unit_type',
    });

    const currentAmenityIds = new Set(
        (unitType?.amenities ?? []).map((amenity) => amenity.id),
    );
    const availableAmenities = [
        ...amenities,
        ...(unitType?.amenities ?? []).filter(
            (current) =>
                !amenities.some((amenity) => amenity.id === current.id),
        ),
    ]
        .filter(
            (amenity) =>
                amenity.scope !== 'property' ||
                currentAmenityIds.has(amenity.id),
        )
        .sort((left, right) => left.name.localeCompare(right.name));
    const selectedAmenities = availableAmenities.filter((amenity) =>
        data.amenity_ids.includes(amenity.id),
    );

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            customForm.reset();
            setAmenityPickerOpen(false);
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

    function toggleAmenity(id: number, checked: boolean): void {
        setData(
            'amenity_ids',
            checked
                ? [...new Set([...data.amenity_ids, id])]
                : data.amenity_ids.filter((current) => current !== id),
        );
    }

    function createCustomAmenity(): void {
        if (!customForm.data.name.trim()) {
            return;
        }

        customForm.post(properties.amenities.store.url(property), {
            onSuccess: () => customForm.reset(),
        });
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-2xl">
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

                <div className="min-h-0 flex-1 overflow-y-auto">
                    <form
                        id="unit-type-form"
                        onSubmit={handleSubmit}
                        className="space-y-6 px-4 pt-2 pb-6"
                    >
                        <section className="space-y-4 rounded-lg border p-4">
                            <div className="border-b pb-3">
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Basic information')}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-name">
                                    {t('Name')}
                                </Label>
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
                                        setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'Describe this accommodation type',
                                    )}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Status')}</Label>
                                <div className="flex min-h-9 items-center gap-2 rounded-md border bg-muted/20 px-3">
                                    <StatusBadge
                                        domain="property"
                                        value={
                                            unitType?.is_active === false
                                                ? 'inactive'
                                                : 'active'
                                        }
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        {unitType?.is_active === false
                                            ? t(
                                                  'Inactive Unit Types remain assigned to existing units.',
                                              )
                                            : t(
                                                  'Active Unit Types can be assigned to units.',
                                              )}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section className="space-y-4 rounded-lg border p-4">
                            <div className="border-b pb-3">
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Unit details')}
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
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
                                            setData(
                                                'bedrooms',
                                                event.target.value,
                                            )
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
                                            setData(
                                                'bathrooms',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.bathrooms} />
                                </div>
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
                                            setData(
                                                'size_sqm',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.size_sqm} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="unit-type-furnishing">
                                        {t('Furnishing')}
                                    </Label>
                                    <Select
                                        value={data.furnishing || undefined}
                                        onValueChange={(value) =>
                                            setData('furnishing', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="unit-type-furnishing"
                                            className="w-full"
                                        >
                                            <SelectValue
                                                placeholder={t(
                                                    'Select furnishing',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {furnishingOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {t(option.label)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.furnishing} />
                                </div>
                            </div>
                        </section>

                        <section className="space-y-4 rounded-lg border p-4">
                            <div className="border-b pb-3">
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Amenities')}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Choose reusable features for this Unit Type. Inactive associations stay visible until removed.',
                                    )}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="unit-type-amenities">
                                    {t('Select amenities')}
                                </Label>
                                <Popover
                                    open={amenityPickerOpen}
                                    onOpenChange={setAmenityPickerOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            id="unit-type-amenities"
                                            type="button"
                                            variant="outline"
                                            role="combobox"
                                            aria-expanded={amenityPickerOpen}
                                            className="w-full justify-between font-normal"
                                        >
                                            <span className="truncate text-left">
                                                {selectedAmenities.length === 0
                                                    ? t('No amenities selected')
                                                    : selectedAmenities.length ===
                                                        1
                                                      ? selectedAmenities[0]
                                                            .name
                                                      : `${selectedAmenities.length} ${t('amenities selected')}`}
                                            </span>
                                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        align="start"
                                        className="w-(--radix-popover-trigger-width) p-0"
                                    >
                                        <Command>
                                            <CommandInput
                                                placeholder={t(
                                                    'Search amenities...',
                                                )}
                                            />
                                            <CommandList>
                                                <CommandEmpty>
                                                    {t('No amenities found.')}
                                                </CommandEmpty>
                                                <CommandGroup>
                                                    {availableAmenities.map(
                                                        (amenity) => {
                                                            const selected =
                                                                data.amenity_ids.includes(
                                                                    amenity.id,
                                                                );

                                                            return (
                                                                <CommandItem
                                                                    key={
                                                                        amenity.id
                                                                    }
                                                                    value={`${amenity.name} ${amenity.id}`}
                                                                    disabled={
                                                                        !amenity.is_active &&
                                                                        !selected
                                                                    }
                                                                    onSelect={() =>
                                                                        toggleAmenity(
                                                                            amenity.id,
                                                                            !selected,
                                                                        )
                                                                    }
                                                                >
                                                                    <Checkbox
                                                                        checked={
                                                                            selected
                                                                        }
                                                                        tabIndex={
                                                                            -1
                                                                        }
                                                                        className="pointer-events-none"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span className="min-w-0 flex-1 truncate">
                                                                        {
                                                                            amenity.name
                                                                        }
                                                                    </span>
                                                                    {!amenity.is_active && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {t(
                                                                                'Inactive',
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </CommandItem>
                                                            );
                                                        },
                                                    )}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                <InputError message={errors.amenity_ids} />
                            </div>

                            {selectedAmenities.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {selectedAmenities.map((amenity) => (
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
                                                ` (${t('Inactive')})`}
                                        </Badge>
                                    ))}
                                </div>
                            )}

                            <div className="grid gap-2 rounded-lg border bg-muted/20 p-4">
                                <Label htmlFor="unit-type-custom-amenity">
                                    {t('Add custom Unit Type amenity')}
                                </Label>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        id="unit-type-custom-amenity"
                                        value={customForm.data.name}
                                        onChange={(event) =>
                                            customForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t('e.g. Reading light')}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="sm:shrink-0"
                                        disabled={
                                            customForm.processing ||
                                            !customForm.data.name.trim()
                                        }
                                        onClick={createCustomAmenity}
                                    >
                                        <Plus className="size-4" />
                                        {t('Add amenity')}
                                    </Button>
                                </div>
                                <InputError message={customForm.errors.name} />
                            </div>
                        </section>
                    </form>

                    <section className="space-y-4 border-t px-4 pt-6 pb-6">
                        <div className="border-b pb-3">
                            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Photos')}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {isEdit
                                    ? t(
                                          'Upload, reorder, and manage the photos for this Unit Type.',
                                      )
                                    : t(
                                          'Save this Unit Type before adding photos.',
                                      )}
                            </p>
                        </div>
                        {isEdit && unitType ? (
                            <MediaGalleryManager
                                items={unitType.gallery ?? []}
                                idPrefix={`unit-type-${unitType.id}-edit`}
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
                                    properties.unitTypes.gallery.update.url({
                                        property: property.slug,
                                        unitType: unitType.id,
                                        media: mediaId,
                                    })
                                }
                                destroyUrl={(mediaId) =>
                                    properties.unitTypes.gallery.destroy.url({
                                        property: property.slug,
                                        unitType: unitType.id,
                                        media: mediaId,
                                    })
                                }
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('Photos become available after creation.')}
                            </p>
                        )}
                    </section>
                </div>

                <SheetFooter className="border-t bg-background/95 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => handleOpenChange(false)}
                        disabled={processing}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        form="unit-type-form"
                        type="submit"
                        disabled={processing}
                    >
                        {t(isEdit ? 'Save' : 'Create')}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
