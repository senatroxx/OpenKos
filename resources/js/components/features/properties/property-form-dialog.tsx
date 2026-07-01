import { usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { InputError, PhoneInput, SearchableSelect } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { store, update } from '@/routes/properties';
import type { Property, Region } from '@/types';

export default function PropertyFormSheet({
    property,
    open,
    onOpenChange,
}: {
    property?: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { regions } = usePage<{ regions: Region[] }>().props;

    const isEdit = Boolean(property);
    const formProps = isEdit
        ? { action: update.url(property!), method: 'put' as const }
        : { action: store.url(), method: 'post' as const };

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

    const regionOptions = regions.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    const selectedRegion = regions.find((r) => r.id === selectedRegionId);

    const cityOptions = (selectedRegion?.cities ?? []).map((c) => ({
        value: c.id,
        label: c.name,
    }));

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
                    <Form {...formProps} onSuccess={() => onOpenChange(false)}>
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={property?.name ?? ''}
                                        placeholder="e.g. Kos Melati"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Address</Label>
                                    <textarea
                                        id="address"
                                        name="address"
                                        defaultValue={property?.address ?? ''}
                                        placeholder="Property address"
                                        className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <input
                                    type="hidden"
                                    name="region_id"
                                    value={selectedRegionId ?? ''}
                                />
                                <input
                                    type="hidden"
                                    name="city_id"
                                    value={selectedCityId ?? ''}
                                />

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
                                            name="postal_code"
                                            defaultValue={
                                                property?.postal_code ?? ''
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
                                        defaultValue={property?.phone ?? ''}
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
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
