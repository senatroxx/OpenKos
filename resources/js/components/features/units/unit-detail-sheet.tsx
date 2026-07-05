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
import type { Property, Unit } from '@/types';

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

export default function UnitDetailSheet({
    unit,
    property,
    open,
    onOpenChange,
    onEdit,
    onAssignTenant,
    onMoveOut,
    onMoveUnit,
}: {
    unit?: Unit | null;
    property: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onAssignTenant?: () => void;
    onMoveOut?: () => void;
    onMoveUnit?: () => void;
}) {
    const isOccupied = (unit?.active_leases ?? 0) > 0;
    const allTenants =
        isOccupied && unit?.leases
            ? unit.leases.flatMap(
                  (l) =>
                      l.tenants ?? (l.primary_tenant ? [l.primary_tenant] : []),
              )
            : [];
    const occupantCount = allTenants.length;
    const hasSpace = unit ? occupantCount < unit.capacity : false;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="sm:max-w-lg"
                expandTo={
                    unit && property
                        ? properties.units.show.url({
                              property: property.slug,
                              unit: unit.slug,
                          })
                        : undefined
                }
            >
                <SheetHeader>
                    <SheetTitle>{unit?.name}</SheetTitle>
                    <SheetDescription>
                        Unit details and occupancy
                    </SheetDescription>
                </SheetHeader>

                {unit && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Status
                                </h3>
                                <Badge
                                    className={`${STATUS_COLORS[unit.status] ?? 'bg-gray-400'} text-white`}
                                >
                                    {STATUS_LABELS[unit.status] ?? unit.status}
                                </Badge>
                            </section>

                            {/* Unit Details */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Unit Details
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Floor
                                        </span>
                                        <span>{unit.floor ?? '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Size
                                        </span>
                                        <span className="tabular-nums">
                                            {unit.size_sqm
                                                ? `${unit.size_sqm} m²`
                                                : '—'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Capacity
                                        </span>
                                        <span className="tabular-nums">
                                            {unit.capacity}
                                        </span>
                                    </div>
                                    {unit.active_rates &&
                                        unit.active_rates.length > 0 && (
                                            <div className="border-t pt-2">
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    Pricing
                                                </p>
                                                {unit.active_rates.map(
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
                            {isOccupied && unit?.leases?.[0] && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Current Occupancy
                                    </h3>
                                    <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                        <div>
                                            <p className="mb-2 text-xs text-muted-foreground">
                                                Tenants (
                                                {unit.capacity > 0
                                                    ? `${allTenants.length}/${unit.capacity}`
                                                    : ''}
                                                )
                                            </p>
                                            <div className="space-y-1">
                                                {allTenants.length > 0 ? (
                                                    allTenants.map((t) => (
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
                                                ) : (
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
                                                    unit.leases[0].rent_amount,
                                                )}
                                                {unit.leases[0].billing_label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Reference
                                            </span>
                                            <span className="font-mono text-xs tabular-nums">
                                                {unit.leases[0].reference ??
                                                    '—'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Monthly equivalent
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    unit.leases[0]
                                                        .monthly_equivalent,
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
                                                    unit.leases[0]
                                                        .deposit_amount,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Due day
                                            </span>
                                            <span className="tabular-nums">
                                                {DUE_DAY_LABELS[
                                                    unit.leases[0].rent_due_day
                                                ] ??
                                                    unit.leases[0].rent_due_day}
                                            </span>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Description */}
                            {unit.description && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Description
                                    </h3>
                                    <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                        {unit.description}
                                    </p>
                                </section>
                            )}

                            {/* Notes */}
                            {unit.notes && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </h3>
                                    <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                        {unit.notes}
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
                            {isOccupied && onMoveUnit && (
                                <Button variant="outline" onClick={onMoveUnit}>
                                    Move Unit
                                </Button>
                            )}
                            {hasSpace && onAssignTenant && (
                                <Button onClick={onAssignTenant}>
                                    Assign Tenant
                                    {unit.capacity > 1 ? '(s)' : ''}
                                </Button>
                            )}
                            {unit && property && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            onOpenChange(false);
                                            router.get(
                                                properties.units.leases.index.url(
                                                    {
                                                        property: property.slug,
                                                        unit: unit.slug,
                                                    },
                                                ),
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
                                                properties.units.maintenanceHistory.url(
                                                    {
                                                        property: property.slug,
                                                        unit: unit.slug,
                                                    },
                                                ),
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
