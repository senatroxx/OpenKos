import { useForm, usePage } from '@inertiajs/react';
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
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/properties';
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
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;

    const { data, setData, submit, reset, processing, errors } = useForm({
        name: property?.name ?? '',
        type: property?.type ?? propertyTypes[0]?.slug ?? '',
        address: property?.address ?? '',
        region_id: property?.region_id ?? property?.region?.id ?? null,
        city_id: property?.city_id ?? city?.id ?? null,
        postal_code: property?.postal_code ?? '',
        phone: property?.phone ?? '',
    });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(isEdit ? update(property!) : store(), {
            onSuccess: () => handleOpenChange(false),
        });
    }

    const regionOptions = regions.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    const selectedRegion = regions.find((r) => r.id === data.region_id);

    const cityOptions = (selectedRegion?.cities ?? []).map((c) => ({
        value: c.id,
        label: c.name,
    }));

    return (
        <Sheet
            key={property?.id ?? 'new'}
            open={open}
            onOpenChange={handleOpenChange}
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

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
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
                                onValueChange={(v) => setData('type', v)}
                            >
                                <SelectTrigger id="type" className="w-full">
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
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                                placeholder="Property address"
                            />
                            <InputError message={errors.address} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label>Province</Label>
                                <SearchableSelect
                                    options={regionOptions}
                                    value={data.region_id}
                                    onChange={(val) =>
                                        setData((prev) => ({
                                            ...prev,
                                            region_id: val as number | null,
                                            city_id: null,
                                        }))
                                    }
                                    placeholder="Select province..."
                                    searchPlaceholder="Search province..."
                                    emptyText="No province found."
                                />
                                <InputError message={errors.region_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label>City / Kabupaten</Label>
                                <SearchableSelect
                                    options={cityOptions}
                                    value={data.city_id}
                                    onChange={(val) =>
                                        setData('city_id', val as number | null)
                                    }
                                    placeholder={
                                        data.region_id
                                            ? 'Select city...'
                                            : 'Select province first'
                                    }
                                    searchPlaceholder="Search city..."
                                    emptyText="No city found."
                                    disabled={!data.region_id}
                                />
                                <InputError message={errors.city_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="postal_code">Postal Code</Label>
                                <Input
                                    id="postal_code"
                                    value={data.postal_code}
                                    onChange={(e) =>
                                        setData('postal_code', e.target.value)
                                    }
                                    placeholder="Postal code"
                                />
                                <InputError message={errors.postal_code} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <PhoneInput
                                value={data.phone}
                                onChange={(v) => setData('phone', v)}
                                placeholder="e.g. 81234567890"
                            />
                            <InputError message={errors.phone} />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button disabled={processing}>
                            {isEdit ? 'Save' : 'Create'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
