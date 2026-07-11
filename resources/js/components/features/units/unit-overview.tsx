import { StatusBadge } from '@/components/shared/status-badge';
import type { Unit } from '@/types';

function formatPrice(cents: string): string {
    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

export default function UnitOverview({ unit }: { unit: Unit }) {
    const isOccupied = (unit.active_leases ?? 0) > 0;
    const allTenants =
        isOccupied && unit.leases
            ? unit.leases.flatMap(
                  (l) =>
                      l.tenants ?? (l.primary_tenant ? [l.primary_tenant] : []),
              )
            : [];

    return (
        <div className="space-y-6">
            <div>
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    Status
                </p>
                <StatusBadge domain="unit" value={unit.status} />
            </div>

            <div>
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    Unit Details
                </p>
                <div className="grid grid-cols-2 gap-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Floor</p>
                        <p className="mt-1 text-sm font-medium">
                            {unit.floor ?? '—'}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Size</p>
                        <p className="mt-1 text-sm font-medium tabular-nums">
                            {unit.size_sqm ? `${unit.size_sqm} m²` : '—'}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">
                            Capacity
                        </p>
                        <p className="mt-1 text-sm font-medium tabular-nums">
                            {unit.capacity}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">
                            Occupants
                        </p>
                        <p className="mt-1 text-sm font-medium tabular-nums">
                            {allTenants.length} / {unit.capacity}
                        </p>
                    </div>
                </div>

                {unit.active_rates && unit.active_rates.length > 0 && (
                    <div className="mt-4 rounded-lg border bg-muted/30 p-4">
                        <p className="mb-2 text-xs text-muted-foreground">
                            Pricing
                        </p>
                        {unit.active_rates.map((rate, i) => (
                            <div
                                key={i}
                                className="flex items-center justify-between text-sm"
                            >
                                <span className="text-muted-foreground">
                                    {rate.billing_interval} {rate.billing_unit}
                                    {rate.billing_interval > 1 ? 's' : ''}
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatPrice(rate.amount)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {unit.description && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Description
                    </p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                        {unit.description}
                    </p>
                </div>
            )}

            {unit.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Notes
                    </p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                        {unit.notes}
                    </p>
                </div>
            )}
        </div>
    );
}
