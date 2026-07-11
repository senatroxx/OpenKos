import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type { Invoice, PaginatedData, TableMeta, WorkspaceLease } from '@/types';
import { LeaseLayout } from './layout';

const columns: TableColumn<Invoice>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-medium font-mono text-xs',
        render: (inv) => inv.reference ?? '—',
    },
    {
        key: 'period_start',
        label: 'Period',
        sortable: true,
        className: 'text-muted-foreground',
        render: (inv) => formatPeriod(inv.period_start, 'id-ID'),
    },
    {
        key: 'due_date',
        label: 'Due date',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (inv) => formatDate(inv.due_date),
    },
    {
        key: 'total',
        label: 'Total',
        sortable: true,
        className: 'tabular-nums',
        render: (inv) => formatPrice(inv.total),
    },
    {
        key: 'amount_paid',
        label: 'Paid',
        sortable: true,
        className: 'tabular-nums text-muted-foreground',
        render: (inv) => formatPrice(inv.amount_paid),
    },
    {
        key: 'outstanding',
        label: 'Outstanding',
        className: 'tabular-nums',
        render: (inv) => formatPrice(inv.outstanding ?? '0'),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (inv) => <StatusBadge domain="invoice" value={inv.status} />,
    },
];

export default function LeaseInvoices({
    lease,
    invoices,
    sort = '-period_start',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    lease: WorkspaceLease;
    invoices: PaginatedData<Invoice>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <LeaseLayout lease={lease} activeTab="invoices">
            <PluginRegion name="workspace-tab-invoices">
                <WorkspaceTable
                    url={`/leases/${lease.id}/invoices`}
                    noun="invoices"
                    rows={invoices}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status }}
                    defaultSort="-period_start"
                    searchPlaceholder="Search by reference..."
                    emptyMessage="No invoices generated yet."
                />
            </PluginRegion>
        </LeaseLayout>
    );
}
