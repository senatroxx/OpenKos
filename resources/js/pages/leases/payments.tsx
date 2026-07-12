import { FileText } from 'lucide-react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type {
    PaginatedData,
    Payment,
    TableMeta,
    WorkspaceLease,
} from '@/types';
import { LeaseLayout } from './layout';

const columns: TableColumn<Payment>[] = [
    {
        key: 'period_start',
        label: 'Period',
        className: 'font-medium',
        render: (p) =>
            p.invoice ? formatPeriod(p.invoice.period_start, 'id-ID') : '—',
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
        render: (p) => (
            <StatusBadge
                domain="payment"
                value={
                    p.verified_at && p.status === 'confirmed'
                        ? 'verified'
                        : p.status
                }
            />
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
            </PluginRegion>
        </LeaseLayout>
    );
}
