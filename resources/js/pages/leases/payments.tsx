import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Badge } from '@/components/ui/badge';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type {
    PaginatedData,
    Payment,
    RentScheduleEntry,
    TableMeta,
    WorkspaceLease,
} from '@/types';
import { LeaseLayout } from './layout';

const columns: TableColumn<Payment>[] = [
    {
        key: 'period_start',
        label: 'Period',
        className: 'font-medium',
        render: (p) => (p.invoice ? formatPeriod(p.invoice.period_start, 'id-ID') : '—'),
    },
    {
        key: 'payment_date',
        label: 'Paid on',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (p) => (p.payment_date ? formatDate(p.payment_date) : '—'),
    },
    {
        key: 'amount',
        label: 'Amount',
        sortable: true,
        className: 'tabular-nums',
        render: (p) => formatPrice(p.amount),
    },
    {
        key: 'payment_method',
        label: 'Method',
        sortable: true,
        className: 'text-muted-foreground',
        render: (p) =>
            PAYMENT_METHOD_LABELS[p.payment_method] ?? p.payment_method,
    },
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs text-muted-foreground',
        render: (p) => p.reference ?? '—',
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (p) =>
            p.status === 'confirmed' ? (
                <Badge className="bg-green-600 text-white">
                    {p.verified_at ? 'Verified' : 'Paid'}
                </Badge>
            ) : p.status === 'pending' ? (
                <Badge className="bg-amber-500 text-white">
                    Pending Review
                </Badge>
            ) : (
                <Badge className="bg-gray-400 text-white">Cancelled</Badge>
            ),
    },
    {
        key: '_proofs',
        label: 'Proofs',
        render: (p) =>
            p.proofs && p.proofs.length > 0 ? (
                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <FileText className="size-3" />
                    {p.proofs.length}
                </span>
            ) : (
                '—'
            ),
    },
];

function RentSchedule({ lease }: { lease: WorkspaceLease }) {
    const [schedule, setSchedule] = useState<RentScheduleEntry[] | null>(null);

    useEffect(() => {
        let cancelled = false;
        fetch(`/leases/${lease.id}/rent-schedule`)
            .then((r) => r.json())
            .then((d) => {
                if (!cancelled) {
                    setSchedule(d.schedule);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [lease.id]);

    if (schedule === null) {
        return (
            <p className="text-sm text-muted-foreground">Loading schedule...</p>
        );
    }

    if (schedule.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No schedule data available.
            </p>
        );
    }

    const badge: Record<string, { label: string; className: string }> = {
        paid: { label: 'Paid', className: 'bg-green-600 text-white' },
        partial: { label: 'Partial', className: 'bg-blue-600 text-white' },
        overdue: { label: 'Overdue', className: 'bg-red-600 text-white' },
        due: { label: 'Due', className: 'bg-yellow-500 text-white' },
        upcoming: { label: 'Upcoming', className: 'bg-gray-400 text-white' },
        cancelled: {
            label: 'Cancelled',
            className: 'bg-gray-300 text-gray-700',
        },
    };

    return (
        <div className="space-y-2">
            {schedule.map((entry, i) => {
                const s = badge[entry.status] ?? badge.upcoming;

                return (
                    <div
                        key={i}
                        className="flex items-center justify-between rounded-lg border p-3 text-sm"
                    >
                        <div>
                            <p className="font-medium">
                                {formatPeriod(entry.period_start, 'id-ID')}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Due {formatDate(entry.due_date)}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="font-medium tabular-nums">
                                {formatPrice(entry.amount)}
                            </p>
                            <Badge
                                className={`px-1.5 py-0 text-[10px] ${s.className}`}
                            >
                                {s.label}
                            </Badge>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function LeasePayments({
    lease,
    payments,
    sort = '-payment_date',
    search = '',
    status = '',
    payment_method = '',
    per_page = 15,
    table,
}: {
    lease: WorkspaceLease;
    payments: PaginatedData<Payment>;
    sort?: string;
    search?: string;
    status?: string;
    payment_method?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <LeaseLayout lease={lease} activeTab="payments">
            <PluginRegion name="workspace-tab-payments">
                <WorkspaceTable
                    url={`/leases/${lease.id}/payments`}
                    noun="payments"
                    rows={payments}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status, payment_method }}
                    defaultSort="-payment_date"
                    searchPlaceholder="Search by reference..."
                    emptyMessage="No payments recorded yet."
                />

                {lease.status === 'active' && (
                    <div className="mt-6">
                        <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Rent Schedule
                        </h3>
                        <RentSchedule lease={lease} />
                    </div>
                )}
            </PluginRegion>
        </LeaseLayout>
    );
}
