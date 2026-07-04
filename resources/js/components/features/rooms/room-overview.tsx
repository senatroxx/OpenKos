import { Badge } from '@/components/ui/badge';
import type { Room } from '@/types';

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

export default function RoomOverview({ room }: { room: Room }) {
    const isOccupied = (room.active_leases ?? 0) > 0;
    const allTenants = isOccupied && room.leases
        ? room.leases.flatMap((l) => l.tenants ?? (l.primary_tenant ? [l.primary_tenant] : []))
        : [];

    return (
        <div className="space-y-6">
            <div>
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Status</p>
                <Badge className={`${STATUS_COLORS[room.status] ?? 'bg-gray-400'} text-white`}>
                    {STATUS_LABELS[room.status] ?? room.status}
                </Badge>
            </div>

            <div>
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Room Details</p>
                <div className="grid grid-cols-2 gap-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Floor</p>
                        <p className="mt-1 text-sm font-medium">{room.floor ?? '—'}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Size</p>
                        <p className="mt-1 text-sm font-medium tabular-nums">
                            {room.size_sqm ? `${room.size_sqm} m²` : '—'}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Capacity</p>
                        <p className="mt-1 text-sm font-medium tabular-nums">{room.capacity}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-xs text-muted-foreground">Occupants</p>
                        <p className="mt-1 text-sm font-medium tabular-nums">
                            {allTenants.length} / {room.capacity}
                        </p>
                    </div>
                </div>

                {room.active_rates && room.active_rates.length > 0 && (
                    <div className="mt-4 rounded-lg border bg-muted/30 p-4">
                        <p className="mb-2 text-xs text-muted-foreground">Pricing</p>
                        {room.active_rates.map((rate, i) => (
                            <div key={i} className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {rate.billing_interval} {rate.billing_unit}{rate.billing_interval > 1 ? 's' : ''}
                                </span>
                                <span className="tabular-nums font-medium">{formatPrice(rate.amount)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {room.description && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Description</p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">{room.description}</p>
                </div>
            )}

            {room.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Notes</p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">{room.notes}</p>
                </div>
            )}
        </div>
    );
}
