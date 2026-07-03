import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { TenantInfo } from '@/types';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    room: {
        id: number;
        name: string;
        floor: string | null;
        property_id: number;
        property: {
            id: number;
            name: string;
            city: { name: string } | null;
        } | null;
    } | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
    deleted_at?: string | null;
    active_leases_count?: number;
    leases?: Lease[];
};

export default function TenantOverview({ tenant }: { tenant: Tenant }) {
    const activeLease = tenant.leases?.[0];
    const isArchived = Boolean(tenant.deleted_at);

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">Status:</span>
                {isArchived ? (
                    <Badge variant="secondary">Archived</Badge>
                ) : tenant.is_active ? (
                    <Badge className="bg-green-600">Active</Badge>
                ) : (
                    <Badge variant="outline" className="border-amber-300 text-amber-600">Inactive</Badge>
                )}
            </div>

            <div className="rounded-lg border bg-muted/30 p-4">
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Current Lease</p>
                {activeLease ? (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">{activeLease.room?.name ?? 'Unknown Room'}</span>
                            <span className="text-xs font-mono text-muted-foreground">{activeLease.reference}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">{activeLease.room?.property?.name ?? 'Unknown Property'}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {formatDate(activeLease.start_date)}
                                {activeLease.end_date ? ` — ${formatDate(activeLease.end_date)}` : ' — Present'}
                            </span>
                            <span className="font-medium tabular-nums">{formatPrice(activeLease.rent_amount)}/mo</span>
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">No active lease</p>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Phone</p>
                    <p className="mt-1 text-sm">{tenant.phone ?? '—'}</p>
                </div>
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">ID Card (KTP)</p>
                    <p className="mt-1 text-sm tabular-nums">{tenant.id_card_number ?? '—'}</p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Emergency Contact</p>
                    <p className="mt-1 text-sm">{tenant.emergency_contact_name ?? '—'}</p>
                </div>
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Emergency Phone</p>
                    <p className="mt-1 text-sm tabular-nums">{tenant.emergency_contact_phone ?? '—'}</p>
                </div>
            </div>

            {tenant.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Notes</p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">{tenant.notes}</p>
                </div>
            )}
        </div>
    );
}
