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
import type { Property, Room } from '@/types';

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
    const isOccupied = (room?.active_leases ?? 0) > 0;
    const allTenants = isOccupied && room?.leases
        ? room.leases.flatMap((l) => l.tenants ?? (l.primary_tenant ? [l.primary_tenant] : []))
        : [];
    const occupantCount = allTenants.length;
    const hasSpace = room ? occupantCount < room.capacity : false;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{room?.name}</SheetTitle>
                    <SheetDescription>
                        Room details and occupancy
                    </SheetDescription>
                </SheetHeader>

                {room && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
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
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Room Details
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Floor
                                        </span>
                                        <span>{room.floor ?? '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Size
                                        </span>
                                        <span className="tabular-nums">
                                            {room.size_sqm
                                                ? `${room.size_sqm} m²`
                                                : '—'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Capacity
                                        </span>
                                        <span className="tabular-nums">
                                            {room.capacity}
                                        </span>
                                    </div>
                                    {room.active_rates &&
                                        room.active_rates.length > 0 && (
                                            <div className="border-t pt-2">
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    Pricing
                                                </p>
                                                {room.active_rates.map(
                                                    (rate, i) => (
                                                        <div
                                                            key={i}
                                                            className="flex items-center justify-between text-sm"
                                                        >
                                                            <span className="text-muted-foreground">
                                                                {
                                                                    rate.billing_interval
                                                                }{' '}
                                                                {
                                                                    rate.billing_unit
                                                                }
                                                                {rate.billing_interval >
                                                                1
                                                                    ? 's'
                                                                    : ''}
                                                            </span>
                                                            <span className="tabular-nums">
                                                                {formatPrice(
                                                                    rate.amount,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                </div>
                            </section>

                            {/* Current Occupancy */}
                            {isOccupied && room?.leases?.[0] && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Current Occupancy
                                    </h3>
                                    <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                        <div>
                                            <p className="mb-2 text-xs text-muted-foreground">
                                                Tenants ({room.capacity > 0
                                                    ? `${allTenants.length}/${room.capacity}`
                                                    : ''})
                                            </p>
                                            <div className="space-y-1">
                                                {allTenants.length > 0
                                                    ? allTenants.map((t) => (
                                                          <div
                                                              key={t.id}
                                                              className="flex items-center justify-between text-sm"
                                                          >
                                                              <span className="font-medium">
                                                                  {t.name}
                                                              </span>
                                                              {t.pivot
                                                                  ?.is_primary && (
                                                                  <span className="text-[10px] font-medium text-blue-600 uppercase">
                                                                      Primary
                                                                  </span>
                                                              )}
                                                          </div>
                                                      ))
                                                    : (
                                                        <span className="text-sm text-muted-foreground">
                                                            No tenants
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Billing rate
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    room.leases[0].rent_amount,
                                                )}
                                                {room.leases[0].billing_label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Reference
                                            </span>
                                            <span className="font-mono text-xs tabular-nums">
                                                {room.leases[0].reference ?? '—'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Monthly equivalent
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    room.leases[0].monthly_equivalent,
                                                )}
                                                /mo
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Deposit
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    room.leases[0].deposit_amount,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Due day
                                            </span>
                                            <span className="tabular-nums">
                                                {DUE_DAY_LABELS[
                                                    room.leases[0].rent_due_day
                                                ] ?? room.leases[0].rent_due_day}
                                            </span>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Description */}
                            {room.description && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Description
                                    </h3>
                                    <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                        {room.description}
                                    </p>
                                </section>
                            )}

                            {/* Notes */}
                            {room.notes && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </h3>
                                    <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
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
                                <Button
                                    variant="destructive"
                                    onClick={onMoveOut}
                                >
                                    Move Out Tenant
                                </Button>
                            )}
                            {isOccupied && onMoveRoom && (
                                <Button variant="outline" onClick={onMoveRoom}>
                                    Move Room
                                </Button>
                            )}
                            {hasSpace && onAssignTenant && (
                                <Button onClick={onAssignTenant}>
                                    Assign Tenant{room.capacity > 1 ? '(s)' : ''}
                                </Button>
                            )}
                            {room && property && (
                                <>
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
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            onOpenChange(false);
                                            router.get(
                                                properties.rooms.maintenanceHistory.url({
                                                    property: property.id,
                                                    room: room.id,
                                                }),
                                            );
                                        }}
                                    >
                                        Maintenance History
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                            >
                                Close
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
