import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
import properties from '@/routes/properties';

const STATUS_OPTIONS = [
    { value: 'available', label: 'Available' },
    { value: 'occupied', label: 'Occupied' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'unavailable', label: 'Unavailable' },
];

type Room = {
    id: number;
    name: string;
    floor: string | null;
    description: string | null;
    base_price: string;
    size_sqm: string | null;
    capacity: number;
    status: string;
    notes: string | null;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
};

export default function RoomFormSheet({
    room,
    property,
    open,
    onOpenChange,
}: {
    room?: Room | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(room);
    const formAction = isEdit
        ? properties.rooms.update.url({ property: property.id, room: room!.id })
        : properties.rooms.store.url(property.id);
    const formMethod = isEdit ? 'put' as const : 'post' as const;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{isEdit ? 'Edit Room' : 'New Room'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update room details'
                            : `Add a room to ${property.name}`}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form action={formAction} method={formMethod} onSuccess={() => onOpenChange(false)}>
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={room?.name ?? ''}
                                        placeholder="e.g. Room 101"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="floor">Floor</Label>
                                        <Input
                                            id="floor"
                                            name="floor"
                                            defaultValue={room?.floor ?? ''}
                                            placeholder="e.g. 1"
                                        />
                                        <InputError message={errors.floor} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="capacity">Capacity</Label>
                                        <Input
                                            id="capacity"
                                            name="capacity"
                                            type="number"
                                            min={1}
                                            defaultValue={room?.capacity ?? 1}
                                        />
                                        <InputError message={errors.capacity} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="base_price">Base Price (IDR)</Label>
                                    <Input
                                        id="base_price"
                                        name="base_price"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        required
                                        defaultValue={room?.base_price ?? ''}
                                        placeholder="e.g. 1000000"
                                    />
                                    <InputError message={errors.base_price} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="size_sqm">Size (m²)</Label>
                                        <Input
                                            id="size_sqm"
                                            name="size_sqm"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            defaultValue={room?.size_sqm ?? ''}
                                            placeholder="e.g. 20"
                                        />
                                        <InputError message={errors.size_sqm} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            name="status"
                                            defaultValue={room?.status ?? 'available'}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        >
                                            {STATUS_OPTIONS.map((opt) => (
                                                <option key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        defaultValue={room?.description ?? ''}
                                        placeholder="Room description"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        defaultValue={room?.notes ?? ''}
                                        placeholder="Additional notes"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                    <InputError message={errors.notes} />
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
