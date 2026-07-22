import { Head, Link } from '@inertiajs/react';
import TenantLeaseContext from '@/components/features/tenant-portal/lease-context';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import { show } from '@/routes/portal/lease';
import { index } from '@/routes/portal/lease';
import type { Lease, TenantLeaseContext as LeaseContext } from '@/types';

export default function LeaseIndex({
    currentLeases,
    previousLeases,
    leaseContext,
}: {
    currentLeases: Lease[];
    previousLeases: Lease[];
    leaseContext: LeaseContext;
}) {
    return (
        <>
            <Head title="Leases" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Leases</h1>
                    <p className="text-sm text-muted-foreground">
                        Current and previous stays
                    </p>
                </div>

                <TenantLeaseContext
                    leaseContext={leaseContext}
                    hrefForLease={(leaseId) =>
                        index({ query: { lease: leaseId } }).url
                    }
                />

                <LeaseSection
                    title="Current stay"
                    leases={currentLeases}
                    emptyMessage="You do not have an active lease."
                />
                <LeaseSection
                    title="Previous stays"
                    leases={previousLeases}
                    emptyMessage="No previous stays."
                />
            </div>
        </>
    );
}

function LeaseSection({
    title,
    leases,
    emptyMessage,
}: {
    title: string;
    leases: Lease[];
    emptyMessage: string;
}) {
    return (
        <section className="space-y-3">
            <h2 className="font-semibold">{title}</h2>
            {leases.length === 0 ? (
                <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : (
                <div className="space-y-3">
                    {leases.map((lease) => (
                        <Link
                            key={lease.id}
                            href={show(lease)}
                            className="block rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">
                                        {lease.unit?.name ?? 'Unit'}
                                        {lease.unit?.property
                                            ? ` · ${lease.unit.property.name}`
                                            : ''}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(lease.start_date)} –{' '}
                                        {lease.end_date
                                            ? formatDate(lease.end_date)
                                            : 'Ongoing'}
                                    </p>
                                </div>
                                <StatusBadge
                                    domain="lease"
                                    value={lease.status}
                                />
                            </div>
                            <p className="mt-3 text-sm tabular-nums">
                                {lease.rent_amount
                                    ? `${formatPrice(lease.rent_amount)} ${lease.billing_label}`
                                    : '—'}
                            </p>
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}
