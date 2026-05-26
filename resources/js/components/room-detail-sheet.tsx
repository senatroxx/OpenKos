import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type LeaseInfo = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    notes: string | null;
    tenant: TenantInfo | null;
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
    leases: LeaseInfo[];
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
    onAssignTenant,
    onMoveOut,
    onMoveRoom,
}: {
    room?: Room | null;
    property: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onAssignTenant?: () => void;
    onMoveOut?: () => void;
    onMoveRoom?: () => void;
}) {
    const activeLease = room?.leases?.[0];
    const isOccupied = activeLease !== undefined && room?.active_leases > 0;

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

                            {/* Active Tenant Card */}
                            {isOccupied && activeLease && (
                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                        Current Tenant
                                    </p>
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">
                                            {activeLease.tenant?.name ?? 'Unknown'}
                                        </p>
                                        {activeLease.tenant?.phone && (
                                            <p className="text-sm text-muted-foreground">
                                                {activeLease.tenant.phone}
                                            </p>
                                        )}
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Rent:</span>
                                            <span className="tabular-nums font-medium">
                                                {formatPrice(activeLease.monthly_rent)}/mo
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Deposit:</span>
                                            <span className="tabular-nums">
                                                {formatPrice(activeLease.deposit_amount)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Due Day:</span>
                                            <span className="tabular-nums">
                                                {activeLease.rent_due_day}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            )}

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
                                    <p className="mt-1 text-sm whitespace-pre-wrap">{room.notes}</p>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            <Button variant="outline" onClick={() => onOpenChange(false)}>
                                Close
                            </Button>
                            {isOccupied && onMoveOut && (
                                <Button variant="destructive" onClick={onMoveOut}>
                                    Move Out Tenant
                                </Button>
                            )}
                            {isOccupied && onMoveRoom && (
                                <Button variant="outline" onClick={onMoveRoom}>
                                    Move Room
                                </Button>
                            )}
                            {!isOccupied && onAssignTenant && (
                                <Button onClick={onAssignTenant}>
                                    Assign Tenant
                                </Button>
                            )}
                            {room && property && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        onOpenChange(false);
                                        router.get(
                                            properties.rooms.leases.index.url({
                                                property: property.id,
                                                room: room.id,
                                            }),
                                        );
                                    }}
                                >
                                    Lease History
                                </Button>
                            )}
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
