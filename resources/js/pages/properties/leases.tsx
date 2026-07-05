import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { PaginatedData, Property, TableMeta, TenantInfo } from '@/types';
import { PropertyLayout } from './layout';

type LeaseRow = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    status: string;
    unit: { id: number; name: string } | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-green-600',
    terminated: 'bg-gray-400',
};

const columns: TableColumn<LeaseRow>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (l) => l.reference ?? `#${l.id}`,
    },
    {
        key: '_room',
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
        render: (l) => (
            <Badge
                className={`${STATUS_COLORS[l.status] ?? 'bg-gray-400'} text-white`}
            >
                {l.status}
            </Badge>
        ),
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
    leases: PaginatedData<LeaseRow>;
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
