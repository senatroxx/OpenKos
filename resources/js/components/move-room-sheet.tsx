import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';

type Room = {
    id: number;
    name: string;
    capacity: number;
    occupied_count: number;
};

type Property = {
    id: number;
    name: string;
};

type Lease = {
    id: number;
};

export default function MoveRoomSheet({
    property,
    currentRoom,
    availableRooms,
    lease,
    open,
    onOpenChange,
}: {
    property: Property;
    currentRoom: Room;
    availableRooms: Room[];
    lease: Lease;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [targetRoomId, setTargetRoomId] = useState<number | null>(null);

    const roomOptions = availableRooms.map((r) => {
        const spotsLeft = r.capacity - r.occupied_count;
        const suffix = r.occupied_count > 0
            ? ` (${r.occupied_count}/${r.capacity} occupied, ${spotsLeft} spot${spotsLeft === 1 ? '' : 's'} left)`
            : r.capacity > 1
                ? ` (capacity ${r.capacity})`
                : '';

        return {
            value: r.id,
            label: `${r.name}${suffix}`,
        };
    });

    return (
        <Sheet key="move-room" open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Move Tenant</SheetTitle>
                    <SheetDescription>
                        Move tenant from {currentRoom.name} to another room
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={properties.rooms.leases.move.url({
                            property: property.id,
                            room: currentRoom.id,
                            lease: lease.id,
                        })}
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <input
                                    type="hidden"
                                    name="target_room_id"
                                    value={targetRoomId ?? ''}
                                />

                                <div className="grid gap-2">
                                    <Label>Target Room</Label>
                                    <SearchableSelect
                                        options={roomOptions}
                                        value={targetRoomId}
                                        onChange={(val) =>
                                            setTargetRoomId(
                                                val as number | null,
                                            )
                                        }
                                        placeholder="Select target room..."
                                        searchPlaceholder="Search room..."
                                        emptyText="No available rooms."
                                    />
                                    <InputError
                                        message={errors.target_room_id}
                                    />
                                </div>

                                <p className="text-sm text-muted-foreground">
                                    The current lease will be terminated, and a
                                    new lease will be created in the selected
                                    room. The deposit will be transferred.
                                </p>

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
                                        Move Tenant
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
