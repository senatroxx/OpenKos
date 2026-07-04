import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { LeaseInfo } from '@/types';

export default function LeaseHistoryTab({ leases }: { leases: LeaseInfo[] }) {
    if (leases.length === 0) {
        return <p className="text-sm text-muted-foreground">No lease history.</p>;
    }

    return (
        <div className="space-y-2">
            {leases.map((l) => (
                <div key={l.id} className="rounded-lg border p-3 text-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">
                                {l.primary_tenant?.name ?? l.tenants?.[0]?.name ?? 'Unknown'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatDate(l.start_date)} — {l.end_date ? formatDate(l.end_date) : 'ongoing'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="font-medium tabular-nums">{formatPrice(l.rent_amount)}</p>
                            <Badge className={l.status === 'active' ? 'bg-green-600 text-white' : 'bg-gray-400 text-white'}>
                                {l.status === 'active' ? 'Active' : 'Terminated'}
                            </Badge>
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
