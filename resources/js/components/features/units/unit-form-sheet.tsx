import { useForm } from '@inertiajs/react';
import { InputError } from '@/components/shared';
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
import properties from '@/routes/properties';
import type { Property, Unit } from '@/types';

type UnitFormData = {
    name: string;
    floor: string;
    capacity: string;
    size_sqm: string;
    status: string;
    description: string;
    notes: string;
    updated_at: string | null;
};

export default function UnitFormSheet({
    unit,
    property,
    open,
    onOpenChange,
}: {
    unit?: Unit | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(unit);
    const { data, setData, submit, reset, processing, errors } =
        useForm<UnitFormData>({
            name: unit?.name ?? '',
            floor: unit?.floor ?? '',
            capacity: String(unit?.capacity ?? 1),
            size_sqm: unit?.size_sqm ?? '',
            status: unit?.status ?? 'available',
            description: unit?.description ?? '',
            notes: unit?.notes ?? '',
            updated_at: unit?.updated_at ?? null,
        });

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
                ? properties.units.update({
                      property: property.slug,
                      unit: unit!.slug,
                  })
                : properties.units.store(property.slug),
            { onSuccess: () => handleOpenChange(false) },
        );
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{isEdit ? 'Edit Unit' : 'New Unit'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update unit details'
                            : `Add a unit to ${property.name}`}
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
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="e.g. Unit 101"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="floor">Floor</Label>
                                <Input
                                    id="floor"
                                    value={data.floor}
                                    onChange={(event) =>
                                        setData('floor', event.target.value)
                                    }
                                    placeholder="e.g. 1"
                                />
                                <InputError message={errors.floor} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="capacity">Capacity</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min={1}
                                    value={data.capacity}
                                    onChange={(event) =>
                                        setData('capacity', event.target.value)
                                    }
                                />
                                <InputError message={errors.capacity} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="size_sqm">Size (m²)</Label>
                                <Input
                                    id="size_sqm"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.size_sqm}
                                    onChange={(event) =>
                                        setData('size_sqm', event.target.value)
                                    }
                                    placeholder="e.g. 20"
                                />
                                <InputError message={errors.size_sqm} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData('status', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[
                                            {
                                                value: 'available',
                                                label: 'Available',
                                            },
                                            {
                                                value: 'occupied',
                                                label: 'Occupied',
                                            },
                                            {
                                                value: 'maintenance',
                                                label: 'Maintenance',
                                            },
                                            {
                                                value: 'unavailable',
                                                label: 'Unavailable',
                                            },
                                        ].map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                placeholder="Unit description"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                placeholder="Additional notes"
                            />
                            <InputError message={errors.notes} />
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
