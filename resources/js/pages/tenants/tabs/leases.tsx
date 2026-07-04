import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { RoomWithProperty, TenantInfo } from '@/types';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    room: RoomWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

export default function LeasesTab({ leases }: { leases: Lease[] }) {
    if (leases.length === 0) {
        return <p className="text-sm text-muted-foreground">No active leases.</p>;
    }

    return (
        <div className="space-y-2">
            {leases.map((l) => (
                <div key={l.id} className="rounded-lg border p-3 text-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">{l.room?.name ?? 'No room'}</p>
                            <p className="text-xs text-muted-foreground">
                                {l.room?.property?.name}{l.room?.property?.name ? ' · ' : ''}
                                {formatDate(l.start_date)} — {l.end_date ? formatDate(l.end_date) : 'ongoing'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="font-medium tabular-nums">{formatPrice(l.rent_amount)}</p>
                            <Badge className="bg-green-600 text-white">Active</Badge>
                        </div>
                    </div>
                    {l.reference && (
                        <p className="mt-1 text-xs text-muted-foreground">Ref: {l.reference}</p>
                    )}
                </div>
            ))}
        </div>
    );
}
