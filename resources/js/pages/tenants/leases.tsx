import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease, PaginatedData, TableMeta } from '@/types';
import type { WorkspaceTenant } from '@/types';
import { TenantLayout } from './layout';

const columns: TableColumn<Lease>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (l) => l.reference ?? `#${l.id}`,
    },
    {
        key: '_unit',
        label: 'Unit',
        render: (l) => l.unit?.name ?? '—',
    },
    {
        key: '_property',
        label: 'Property',
        render: (l) => l.unit?.property?.name ?? '—',
    },
    {
        key: 'start_date',
        label: 'Start',
        sortable: true,
        render: (l) => formatDate(l.start_date),
    },
    {
        key: 'end_date',
        label: 'End',
        sortable: true,
        render: (l) => formatDate(l.end_date),
    },
    {
        key: 'rent_amount',
        label: 'Rent',
        sortable: true,
        render: (l) => `${formatPrice(l.rent_amount, l.currency)} ${l.billing_label ?? ''}`,
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (l) => <StatusBadge status={l.status} />,
    },
];

export default function TenantLeases({
    tenant,
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    tenant: WorkspaceTenant;
    leases: PaginatedData<Lease>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <TenantLayout tenant={tenant} activeTab="leases">
            <PluginRegion name="workspace-tab-leases">
                <WorkspaceTable
                    url={`/tenants/${tenant.id}/leases`}
                    noun="leases"
                    rows={leases}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status }}
                    defaultSort="-start_date"
                    searchPlaceholder="Search by reference or unit..."
                    emptyMessage="No leases for this tenant yet."
                    onRowClick={(l) => router.get(`/leases/${l.id}`)}
                />
            </PluginRegion>
        </TenantLayout>
    );
}
