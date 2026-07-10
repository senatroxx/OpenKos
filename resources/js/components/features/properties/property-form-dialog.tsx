import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store, update } from '@/actions/App/Http/Controllers/PropertyController';
import { InputError, PhoneInput, SearchableSelect } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Property, PropertyTypeOption, Region } from '@/types';

export default function PropertyFormSheet({
    property,
    open,
    onOpenChange,
}: {
    property?: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { regions, propertyTypes } = usePage<{
        regions: Region[];
        propertyTypes: PropertyTypeOption[];
    }>().props;

    const isEdit = Boolean(property);

    const [selectedRegionId, setSelectedRegionId] = useState<number | null>(
        property?.region_id ?? property?.region?.id ?? null,
    );
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;
    const [selectedCityId, setSelectedCityId] = useState<number | null>(
        property?.city_id ?? city?.id ?? null,
    );

    const { data, setData, processing, errors, submit } = useForm({
        name: property?.name ?? '',
        type: property?.type ?? propertyTypes[0]?.slug ?? '',
        address: property?.address ?? '',
        region_id: property?.region_id ?? property?.region?.id ?? null,
        city_id: property?.city_id ?? city?.id ?? null,
        postal_code: property?.postal_code ?? '',
        phone: property?.phone ?? '',
    });

    const regionOptions = regions.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    const selectedRegion = regions.find((r) => r.id === selectedRegionId);

    const cityOptions = (selectedRegion?.cities ?? []).map((c) => ({
        value: c.id,
        label: c.name,
    }));

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(isEdit ? update(property!) : store(), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Sheet
            key={property?.id ?? 'new'}
            open={open}
            onOpenChange={onOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isEdit ? 'Edit Property' : 'New Property'}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update property details'
                            : 'Add a new property to manage'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-6 pt-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    required
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Kos Melati"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(v) =>
                                        setData('type', v)
                                    }
                                >
                                    <SelectTrigger
                                        id="type"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {propertyTypes.map((opt) => (
                                            <SelectItem
                                                key={opt.slug}
                                                value={opt.slug}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>
                                <textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                    placeholder="Property address"
                                    className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label>Province</Label>
                                        <SearchableSelect
                                            options={regionOptions}
                                            value={selectedRegionId}
                                            onChange={(val) => {
                                                setSelectedRegionId(
                                                    val as number | null,
                                                );
                                                setSelectedCityId(null);
                                                setData(
                                                    'region_id',
                                                    val as number | null,
                                                );
                                                setData('city_id', null);
                                            }}
                                            placeholder="Select province..."
                                            searchPlaceholder="Search province..."
                                            emptyText="No province found."
                                        />
                                        <InputError
                                            message={errors.region_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>City / Kabupaten</Label>
                                        <SearchableSelect
                                            options={cityOptions}
                                            value={selectedCityId}
                                            onChange={(val) => {
                                                setSelectedCityId(
                                                    val as number | null,
                                                );
                                                setData(
                                                    'city_id',
                                                    val as number | null,
                                                );
                                            }}
                                            placeholder={
                                                selectedRegionId
                                                    ? 'Select city...'
                                                    : 'Select province first'
                                            }
                                            searchPlaceholder="Search city..."
                                            emptyText="No city found."
                                            disabled={!selectedRegionId}
                                        />
                                        <InputError message={errors.city_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="postal_code">
                                            Postal Code
                                        </Label>
                                        <Input
                                            id="postal_code"
                                            value={data.postal_code}
                                            onChange={(e) =>
                                                setData(
                                                    'postal_code',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Postal code"
                                        />
                                        <InputError
                                            message={errors.postal_code}
                                        />
                                    </div>
                                </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <PhoneInput
                                    name="phone"
                                    defaultValue={data.phone}
                                    placeholder="e.g. 81234567890"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="flex items-center justify-end gap-4 pt-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button disabled={processing}>
                                    {isEdit ? 'Save' : 'Create'}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
