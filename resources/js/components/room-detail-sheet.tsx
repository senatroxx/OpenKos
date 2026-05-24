import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';

const STATUS_LABELS: Record<string, string> = {
    available: 'Available',
    occupied: 'Occupied',
    maintenance: 'Maintenance',
    unavailable: 'Unavailable',
};

const STATUS_COLORS: Record<string, string> = {
    available: 'bg-green-600',
    occupied: 'bg-blue-600',
    maintenance: 'bg-amber-500',
    unavailable: 'bg-gray-400',
};

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
    active_leases: number;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
};

function formatPrice(cents: string): string {
    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

export default function RoomDetailSheet({
    room,
    property,
    open,
    onOpenChange,
    onEdit,
}: {
    room?: Room | null;
    property: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
}) {
    function destroy() {
        if (!room || !property) {
            return;
        }

        if (confirm('Are you sure you want to delete this room?')) {
            router.delete(properties.rooms.destroy.url({ property: property.id, room: room.id }), {
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{room?.name}</SheetTitle>
                    <SheetDescription>
                        {room?.floor ? `Floor ${room.floor}` : 'Room details'}
                    </SheetDescription>
                </SheetHeader>

                {room && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pb-6 pt-4">
                        <div className="space-y-5">
                            <div className="flex items-center gap-2">
                                <span>Status:</span>
                                <Badge
                                    className={`${STATUS_COLORS[room.status] ?? 'bg-gray-400'} text-white`}
                                >
                                    {STATUS_LABELS[room.status] ?? room.status}
                                </Badge>
                            </div>

                            {room.description && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Description
                                    </p>
                                    <p className="mt-1 text-sm">{room.description}</p>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Price
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">
                                        {formatPrice(room.base_price)}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Size
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">
                                        {room.size_sqm ? `${room.size_sqm} m²` : '—'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Floor
                                    </p>
                                    <p className="mt-1 text-sm">{room.floor ?? '—'}</p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Capacity
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">{room.capacity}</p>
                                </div>
                            </div>

                            {room.notes && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Notes
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {room.notes}
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="flex items-center justify-end gap-4">
                            <Button variant="outline" onClick={() => onOpenChange(false)}>
                                Close
                            </Button>
                            <Button variant="destructive" onClick={destroy}>
                                Delete
                            </Button>
                            <Button
                                onClick={() => {
                                    onOpenChange(false);
                                    onEdit();
                                }}
                            >
                                Edit
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
