import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease, WorkspaceTenant } from '@/types';

export default function TenantOverview({
    tenant,
}: {
    tenant: WorkspaceTenant & { leases?: Lease[] };
}) {
    const activeLease = tenant.leases?.[0];
    const isArchived = Boolean(tenant.deleted_at);

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">Status:</span>
                {
                    <StatusBadge
                        domain="tenant"
                        value={
                            isArchived
                                ? 'archived'
                                : tenant.is_active
                                  ? 'active'
                                  : 'inactive'
                        }
                    />
                }
            </div>

            <div className="rounded-lg border bg-muted/30 p-4">
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    Current Lease
                </p>
                {activeLease ? (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">
                                {activeLease.unit?.name ?? 'Unknown Unit'}
                            </span>
                            <span className="font-mono text-xs text-muted-foreground">
                                {activeLease.reference}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {activeLease.unit?.property?.name ??
                                    'Unknown Property'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {formatDate(activeLease.start_date)}
                                {activeLease.end_date
                                    ? ` — ${formatDate(activeLease.end_date)}`
                                    : ' — Present'}
                            </span>
                            <span className="font-medium tabular-nums">
                                {formatPrice(activeLease.rent_amount)}/mo
                            </span>
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No active lease
                    </p>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Phone
                    </p>
                    <p className="mt-1 text-sm">{tenant.phone ?? '—'}</p>
                </div>
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        ID Card (KTP)
                    </p>
                    <p className="mt-1 text-sm tabular-nums">
                        {tenant.id_card_number ?? '—'}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Emergency Contact
                    </p>
                    <p className="mt-1 text-sm">
                        {tenant.emergency_contact_name ?? '—'}
                    </p>
                </div>
                <div className="rounded-lg border bg-muted/30 p-4">
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Emergency Phone
                    </p>
                    <p className="mt-1 text-sm tabular-nums">
                        {tenant.emergency_contact_phone ?? '—'}
                    </p>
                </div>
            </div>

            {tenant.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Notes
                    </p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                        {tenant.notes}
                    </p>
                </div>
            )}
        </div>
    );
}
