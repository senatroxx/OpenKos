import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type RoomRate = {
    id: number;
    billing_interval: number;
    billing_unit: 'day' | 'week' | 'month' | 'year';
    amount: string;
};

type LeaseInfo = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    billing_interval: number;
    billing_unit: string;
    monthly_equivalent: string;
    billing_label: string;
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
    active_rates: RoomRate[];
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

const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day',
};

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
    const isOccupied = activeLease !== undefined && (room?.active_leases ?? 0) > 0;
    const tenantName = activeLease?.tenant?.name ?? '—';
    const phone = activeLease?.tenant?.phone;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{room?.name}</SheetTitle>
                </SheetHeader>

                {room && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pb-6 pt-4">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    Status
                                </h3>
                                <Badge
                                    className={`${STATUS_COLORS[room.status] ?? 'bg-gray-400'} text-white`}
                                >
                                    {STATUS_LABELS[room.status] ?? room.status}
                                </Badge>
                            </section>

                            {/* Room Details */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    Room Details
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Floor</span>
                                        <span>{room.floor ?? '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Size</span>
                                        <span className="tabular-nums">
                                            {room.size_sqm ? `${room.size_sqm} m²` : '—'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Capacity</span>
                                        <span className="tabular-nums">{room.capacity}</span>
                                    </div>
                                    {room.active_rates && room.active_rates.length > 0 && (
                                        <div className="border-t pt-2">
                                            <p className="mb-2 text-xs text-muted-foreground">Pricing</p>
                                            {room.active_rates.map((rate, i) => (
                                                <div key={i} className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {rate.billing_interval} {rate.billing_unit}{rate.billing_interval > 1 ? 's' : ''}
                                                    </span>
                                                    <span className="tabular-nums">{formatPrice(rate.amount)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {/* Current Occupancy */}
                            {isOccupied && activeLease && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Current Occupancy
                                    </h3>
                                    <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Tenant</span>
                                            <div className="text-right">
                                                <p className="text-sm font-medium">{tenantName}</p>
                                                {phone && (
                                                    <p className="text-xs text-muted-foreground">{phone}</p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Billing rate</span>
                                            <span className="tabular-nums font-medium">
                                                {formatPrice(activeLease.rent_amount)}{activeLease.billing_label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Monthly equivalent</span>
                                            <span className="tabular-nums">
                                                {formatPrice(activeLease.monthly_equivalent)}/mo
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Deposit</span>
                                            <span className="tabular-nums">
                                                {formatPrice(activeLease.deposit_amount)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Due day</span>
                                            <span className="tabular-nums">
                                                {DUE_DAY_LABELS[activeLease.rent_due_day] ?? activeLease.rent_due_day}
                                            </span>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Description */}
                            {room.description && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Description
                                    </h3>
                                    <p className="text-sm whitespace-pre-wrap rounded-lg border p-4">
                                        {room.description}
                                    </p>
                                </section>
                            )}

                            {/* Notes */}
                            {room.notes && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Notes
                                    </h3>
                                    <p className="text-sm whitespace-pre-wrap rounded-lg border p-4">
                                        {room.notes}
                                    </p>
                                </section>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {onEdit && (
                                <Button variant="outline" onClick={onEdit}>
                                    Edit
                                </Button>
                            )}
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
                            <Button variant="ghost" onClick={() => onOpenChange(false)}>
                                Close
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
