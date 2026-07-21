import { StatusBadge } from '@/components/shared/status-badge';
import { DUE_DAY_LABELS } from '@/lib/constants';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease } from '@/types';
import { TenantLeaseLayout } from './layout';

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right">{value}</span>
        </div>
    );
}

export default function LeaseWorkspace({ lease }: { lease: Lease }) {
    return (
        <TenantLeaseLayout lease={lease} activeTab="overview">
            <div className="space-y-6">
                <section>
                    <h2 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Status
                    </h2>
                    <StatusBadge domain="lease" value={lease.status} />
                </section>

                <section>
                    <h2 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Stay
                    </h2>
                    <div className="space-y-2 rounded-lg border p-4 text-sm">
                        <Detail
                            label="Unit"
                            value={
                                lease.unit
                                    ? `${lease.unit.name}${lease.unit.property ? ` - ${lease.unit.property.name}` : ''}`
                                    : '—'
                            }
                        />
                        <Detail label="Start date" value={formatDate(lease.start_date)} />
                        <Detail label="End date" value={lease.end_date ? formatDate(lease.end_date) : 'Ongoing'} />
                    </div>
                </section>

                <section>
                    <h2 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Rent
                    </h2>
                    <div className="space-y-2 rounded-lg border p-4 text-sm">
                        <Detail
                            label="Billing rate"
                            value={`${formatPrice(lease.rent_amount)} ${lease.billing_label ?? ''}`}
                        />
                        <Detail
                            label="Due date"
                            value={
                                DUE_DAY_LABELS[lease.rent_due_day] ??
                                `${lease.rent_due_day}th`
                            }
                        />
                    </div>
                </section>
            </div>
        </TenantLeaseLayout>
    );
}
