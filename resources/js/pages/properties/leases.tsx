import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease, PaginatedData, Property, TableMeta } from '@/types';
import { PropertyLayout } from './layout';

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
        className: 'font-medium',
        render: (l) => l.unit?.name ?? '—',
    },
    {
        key: '_tenants',
        label: 'Tenants',
        render: (l) => {
            const occupants = l.tenants.length
                ? l.tenants
                : l.primary_tenant
                  ? [l.primary_tenant]
                  : [];

            return occupants.length ? (
                <div className="text-sm">
                    {occupants.map((t) => (
                        <div key={t.id}>{t.name}</div>
                    ))}
                </div>
            ) : (
                '—'
            );
        },
    },
    {
        key: 'start_date',
        label: 'Start',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => formatDate(l.start_date),
    },
    {
        key: 'end_date',
        label: 'End',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => (l.end_date ? formatDate(l.end_date) : 'ongoing'),
    },
    {
        key: 'rent_amount',
        label: 'Rent',
        sortable: true,
        className: 'tabular-nums',
        render: (l) => formatPrice(l.rent_amount),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (l) => <StatusBadge domain="lease" value={l.status} />,
    },
];

export default function PropertyLeases({
    property,
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    property: Property;
    leases: PaginatedData<Lease>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <PropertyLayout property={property} activeTab="leases">
            <PluginRegion name="workspace-tab-leases">
                <WorkspaceTable
                    url={`/properties/${property.slug}/leases`}
                    noun="leases"
                    rows={leases}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status }}
                    defaultSort="-start_date"
                    searchPlaceholder="Search by reference, tenant, or unit..."
                    emptyMessage="No leases for this property yet."
                    onRowClick={(l) => router.get(`/leases/${l.id}`)}
                />
            </PluginRegion>
        </PropertyLayout>
    );
}
