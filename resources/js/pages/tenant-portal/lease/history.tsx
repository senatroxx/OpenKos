import type { TableColumn } from '@/components/data-table';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate } from '@/lib/formatters';
import { history } from '@/routes/portal/lease';
import type { Lease, PaginatedData, TableMeta } from '@/types';
import { TenantLeaseLayout } from './layout';

type UnitHistory = {
    id: number;
    from_unit: { id: number; name: string } | null;
    to_unit: { id: number; name: string } | null;
    reason: string | null;
    effective_date: string;
};

const columns: TableColumn<UnitHistory>[] = [
    {
        key: 'effective_date',
        label: 'Date',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (entry) => formatDate(entry.effective_date),
    },
    {
        key: '_move',
        label: 'Move',
        className: 'font-medium',
        render: (entry) =>
            `${entry.from_unit?.name ?? '—'} to ${entry.to_unit?.name ?? '—'}`,
    },
    {
        key: 'reason',
        label: 'Reason',
        sortable: true,
        render: (entry) => entry.reason?.replaceAll('_', ' ') ?? '—',
    },
];

export default function LeaseHistory({
    lease,
    history: data,
    sort = '-effective_date',
    search = '',
    per_page = 15,
    table,
}: {
    lease: Lease;
    history: PaginatedData<UnitHistory>;
    sort?: string;
    search?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <TenantLeaseLayout lease={lease} activeTab="history">
            <WorkspaceTable
                url={history.url(lease)}
                noun="history entries"
                rows={data}
                columns={columns}
                tableMeta={table}
                sort={sort}
                search={search}
                perPage={per_page}
                defaultSort="-effective_date"
                searchPlaceholder="Search by reason..."
                emptyMessage="No unit changes recorded for this lease."
            />
        </TenantLeaseLayout>
    );
}
