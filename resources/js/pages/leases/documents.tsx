import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate } from '@/lib/formatters';
import type { PaginatedData, TableMeta } from '@/types';
import type { WorkspaceLease } from './layout';
import { LeaseLayout } from './layout';

type ProofRow = {
    id: number;
    payment_id: number;
    original_name: string;
    mime_type: string;
    created_at: string;
    payment: {
        id: number;
        invoice_id: number;
        amount: string;
        status: string;
        invoice: {
            id: number;
            period_start: string;
        } | null;
    } | null;
};

function formatPeriod(periodStart: string): string {
    return new Date(periodStart).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
    });
}

const columns: TableColumn<ProofRow>[] = [
    {
        key: 'original_name',
        label: 'Name',
        sortable: true,
        className: 'font-medium',
        render: (d) => (
            <a
                href={`/payments/${d.payment_id}/proof/${d.id}`}
                target="_blank"
                rel="noreferrer"
                onClick={(e) => e.stopPropagation()}
                className="hover:underline"
            >
                {d.original_name}
            </a>
        ),
    },
    {
        key: '_period',
        label: 'Payment period',
        className: 'text-muted-foreground',
        render: (d) =>
            d.payment?.invoice?.period_start
                ? formatPeriod(d.payment.invoice.period_start)
                : '—',
    },
    {
        key: 'mime_type',
        label: 'Type',
        sortable: true,
        className: 'text-xs text-muted-foreground',
    },
    {
        key: 'created_at',
        label: 'Uploaded',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (d) => formatDate(d.created_at),
    },
];

export default function LeaseDocuments({
    lease,
    documents,
    sort = '-created_at',
    search = '',
    payment_status = '',
    per_page = 15,
    table,
}: {
    lease: WorkspaceLease;
    documents: PaginatedData<ProofRow>;
    sort?: string;
    search?: string;
    payment_status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <LeaseLayout lease={lease} activeTab="documents">
            <PluginRegion name="workspace-tab-documents">
                <WorkspaceTable
                    url={`/leases/${lease.id}/documents`}
                    noun="documents"
                    rows={documents}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ payment_status }}
                    defaultSort="-created_at"
                    searchPlaceholder="Search by file name..."
                    emptyMessage="No payment proofs uploaded yet."
                />
            </PluginRegion>
        </LeaseLayout>
    );
}
