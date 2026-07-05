import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { PaginatedData, TableMeta, TenantInfo } from '@/types';
import { RoomLayout } from './layout';

type LeaseRow = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    status: string;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
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
        key: '_tenants',
        label: 'Tenants',
        className: 'font-medium',
        render: (l) => {
            const occupants = l.tenants.length
                ? l.tenants
                : l.primary_tenant
                  ? [l.primary_tenant]
                  : [];

            return occupants.length
                ? occupants.map((t) => t.name).join(', ')
                : '—';
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
                className={
                    l.status === 'active'
                        ? 'bg-green-600 text-white'
                        : 'bg-gray-400 text-white'
                }
            >
                {l.status}
            </Badge>
        ),
    },
];

export default function RoomLeaseHistory({
    property,
    room,
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    property: { id: number; slug: string; name: string };
    room: {
        id: number;
        slug: string;
        name: string;
        floor?: string | number | null;
    };
    leases: PaginatedData<LeaseRow>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <RoomLayout property={property} room={room} activeTab="lease-history">
            <PluginRegion name="workspace-tab-lease-history">
                <WorkspaceTable
                    url={`/properties/${property.slug}/rooms/${room.slug}/lease-history`}
                    noun="leases"
                    rows={leases}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status }}
                    defaultSort="-start_date"
                    searchPlaceholder="Search by reference or tenant..."
                    emptyMessage="No lease history for this room."
                    onRowClick={(l) => router.get(`/leases/${l.id}`)}
                />
            </PluginRegion>
        </RoomLayout>
    );
}
