import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { InputError, SearchableSelect } from '@/components/shared';
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
export default function MoveUnitSheet({
    property,
    currentRoom,
    availableUnits,
    lease,
    open,
    onOpenChange,
}: {
    property: { id: number; slug: string; name: string };
    currentRoom: { id: number; slug: string; name: string; capacity: number };
    availableUnits: {
        id: number;
        name: string;
        capacity: number;
        occupied_count?: number;
    }[];
    lease: { id: number };
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [targetRoomId, setTargetRoomId] = useState<number | null>(null);

    const roomOptions = availableUnits.map((r) => {
        const occupiedCount = r.occupied_count ?? 0;
        const spotsLeft = r.capacity - occupiedCount;
        const suffix =
            occupiedCount > 0
                ? ` (${occupiedCount}/${r.capacity} occupied, ${spotsLeft} spot${spotsLeft === 1 ? '' : 's'} left)`
                : r.capacity > 1
                  ? ` (capacity ${r.capacity})`
                  : '';

        return {
            value: r.id,
            label: `${r.name}${suffix}`,
        };
    });

    return (
        <Sheet key="move-unit" open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Move Tenant</SheetTitle>
                    <SheetDescription>
                        Move tenant from {currentRoom.name} to another unit
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={properties.units.leases.move.url({
                            property: property.slug,
                            unit: currentRoom.slug,
                            lease: lease.id,
                        })}
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <input
                                    type="hidden"
                                    name="target_unit_id"
                                    value={targetRoomId ?? ''}
                                />

                                <div className="grid gap-2">
                                    <Label>Target Unit</Label>
                                    <SearchableSelect
                                        options={roomOptions}
                                        value={targetRoomId}
                                        onChange={(val) =>
                                            setTargetRoomId(
                                                val as number | null,
                                            )
                                        }
                                        placeholder="Select target unit..."
                                        searchPlaceholder="Search unit..."
                                        emptyText="No available units."
                                    />
                                    <InputError
                                        message={errors.target_unit_id}
                                    />
                                </div>

                                <p className="text-sm text-muted-foreground">
                                    The current lease will be terminated, and a
                                    new lease will be created in the selected
                                    unit. The deposit will be transferred.
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
