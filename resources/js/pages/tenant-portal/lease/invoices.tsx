import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { invoices } from '@/routes/portal/lease';
import { show } from '@/routes/portal/lease/invoices';
import type { Invoice, Lease, PaginatedData, TableMeta } from '@/types';
import { TenantLeaseLayout } from './layout';

const columns: TableColumn<Invoice>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (invoice) => invoice.reference ?? '—',
    },
    {
        key: 'period_start',
        label: 'Period',
        sortable: true,
        className: 'text-muted-foreground',
        render: (invoice) => formatPeriod(invoice.period_start, 'id-ID'),
    },
    {
        key: 'due_date',
        label: 'Due date',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (invoice) => formatDate(invoice.due_date),
    },
    {
        key: 'total',
        label: 'Total',
        sortable: true,
        className: 'tabular-nums',
        render: (invoice) => formatPrice(invoice.total),
    },
    {
        key: 'amount_paid',
        label: 'Paid',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (invoice) => formatPrice(invoice.amount_paid),
    },
    {
        key: 'outstanding',
        label: 'Outstanding',
        className: 'tabular-nums',
        render: (invoice) => formatPrice(invoice.outstanding ?? '0'),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (invoice) => (
            <StatusBadge
                domain="invoice"
                value={invoice.display_status ?? invoice.status}
            />
        ),
    },
];

export default function LeaseInvoices({
    lease,
    invoices: data,
    sort = '-period_start',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    lease: Lease;
    invoices: PaginatedData<Invoice>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <TenantLeaseLayout lease={lease} activeTab="invoices">
            <WorkspaceTable
                url={invoices.url(lease)}
                noun="invoices"
                rows={data}
                columns={columns}
                tableMeta={table}
                sort={sort}
                search={search}
                perPage={per_page}
                filterValues={{ status }}
                defaultSort="-period_start"
                searchPlaceholder="Search by reference..."
                emptyMessage="No invoices generated yet."
                onRowClick={(invoice) =>
                    router.get(show.url({ lease, invoice }))
                }
            />
        </TenantLeaseLayout>
    );
}
