import { router } from '@inertiajs/react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { DUE_DAY_LABELS } from '@/lib/constants';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Property, Unit } from '@/types';

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
                        {t('Unit details and occupancy')}
                    </SheetDescription>
                </SheetHeader>

                {unit && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Status')}
                                </h3>
                                <StatusBadge
                                    domain="unit"
                                    value={unit.status}
                                />
                            </section>

                            {/* Unit Details */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Unit Details')}
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t('Floor')}
                                        </span>
                                        <span>{unit.floor ?? '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t('Size')}
                                        </span>
                                        <span className="tabular-nums">
                                            {unit.size_sqm
                                                ? `${unit.size_sqm} m²`
                                                : '—'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {t('Capacity')}
                                        </span>
                                        <span className="tabular-nums">
                                            {unit.capacity}
                                        </span>
                                    </div>
                                    {unit.active_rates &&
                                        unit.active_rates.length > 0 && (
                                            <div className="border-t pt-2">
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    {t('Pricing')}
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
                                                                    rate.currency,
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
                                        {t('Current Occupancy')}
                                    </h3>
                                    <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                        <div>
                                            <p className="mb-2 text-xs text-muted-foreground">
                                                {t('Tenants')} (
                                                {unit.capacity > 0
                                                    ? `${allTenants.length}/${unit.capacity}`
                                                    : ''}
                                                )
                                            </p>
                                            <div className="space-y-1">
                                                {allTenants.length > 0 ? (
                                                    allTenants.map((tenant) => (
                                                        <div
                                                            key={tenant.id}
                                                            className="flex items-center justify-between text-sm"
                                                        >
                                                            <span className="font-medium">
                                                                {tenant.name}
                                                            </span>
                                                            {tenant.pivot
                                                                ?.is_primary && (
                                                                <span className="text-xs font-medium text-primary uppercase">
                                                                    {t(
                                                                        'Primary',
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ))
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        {t('No tenants')}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('Billing rate')}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    unit.leases[0].rent_amount,
                                                    unit.leases[0].currency,
                                                )}
                                                {unit.leases[0].billing_label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('Reference')}
                                            </span>
                                            <span className="font-mono text-xs tabular-nums">
                                                {unit.leases[0].reference ??
                                                    '—'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('Monthly equivalent')}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    unit.leases[0]
                                                        .monthly_equivalent,
                                                    unit.leases[0].currency,
                                                )}
                                                /mo
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('Deposit')}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    unit.leases[0]
                                                        .deposit_amount,
                                                    unit.leases[0].currency,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('Due day')}
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
                                        {t('Description')}
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
                                        {t('Notes')}
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
                                    {t('Edit')}
                                </Button>
                            )}
                            {isOccupied && onMoveOut && (
                                <Button
                                    variant="destructive"
                                    onClick={onMoveOut}
                                >
                                    {t('Move Out Tenant')}
                                </Button>
                            )}
                            {isOccupied && onMoveUnit && (
                                <Button variant="outline" onClick={onMoveUnit}>
                                    {t('Move Unit')}
                                </Button>
                            )}
                            {hasSpace && onAssignTenant && (
                                <Button onClick={onAssignTenant}>
                                    {t(
                                        unit.capacity > 1
                                            ? 'Assign Tenants'
                                            : 'Assign Tenant',
                                    )}
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
                                        {t('Lease History')}
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
                                        {t('Maintenance History')}
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                {t('Close')}
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
