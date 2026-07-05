import { router } from '@inertiajs/react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { MaintenanceTicket, PaginatedData, TableMeta } from '@/types';
import { UnitLayout } from './layout';

const priorityColors: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700',
    medium: 'bg-yellow-100 text-yellow-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};

const statusColors: Record<string, string> = {
    reported: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-purple-100 text-purple-700',
    resolved: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const priorityLabel: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

const statusLabel: Record<string, string> = {
    reported: 'Reported',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

const columns: TableColumn<MaintenanceTicket>[] = [
    {
        key: '_reference',
        label: 'ID',
        className: 'font-mono text-xs text-muted-foreground',
        render: (t) => t.reference ?? `#${t.id}`,
    },
    {
        key: 'title',
        label: 'Title',
        sortable: true,
        className: 'font-medium',
    },
    {
        key: 'priority',
        label: 'Priority',
        sortable: true,
        render: (t) => (
            <Badge className={priorityColors[t.priority] ?? ''}>
                {priorityLabel[t.priority] ?? t.priority}
            </Badge>
        ),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (t) => (
            <Badge className={statusColors[t.status] ?? ''}>
                {statusLabel[t.status] ?? t.status}
            </Badge>
        ),
    },
    {
        key: 'cost',
        label: 'Cost',
        sortable: true,
        className: 'tabular-nums',
        render: (t) => (t.cost ? formatPrice(t.cost) : '—'),
    },
    {
        key: '_assignee',
        label: 'Assigned To',
        className: 'text-muted-foreground',
        render: (t) => t.assignee?.name ?? '—',
    },
    {
        key: 'created_at',
        label: 'Created',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (t) => formatDate(t.created_at),
    },
    {
        key: 'resolved_at',
        label: 'Resolved',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (t) => (t.resolved_at ? formatDate(t.resolved_at) : '—'),
    },
];

export default function MaintenanceHistory({
    property,
    unit,
    tickets,
    sort = '-created_at',
    search = '',
    status = '',
    priority = '',
    per_page = 15,
    table,
}: {
    property: { id: number; slug: string; name: string };
    unit: {
        id: number;
        slug: string;
        name: string;
        floor?: string | number | null;
    };
    tickets: PaginatedData<MaintenanceTicket>;
    sort?: string;
    search?: string;
    status?: string;
    priority?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <UnitLayout property={property} unit={unit} activeTab="maintenance">
            <PluginRegion name="workspace-tab-maintenance">
                <WorkspaceTable
                    url={`/properties/${property.slug}/units/${unit.slug}/maintenance-history`}
                    noun="tickets"
                    rows={tickets}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status, priority }}
                    defaultSort="-created_at"
                    searchPlaceholder="Search by title or reference..."
                    emptyMessage="No maintenance history for this unit."
                    onRowClick={(t) =>
                        router.get(`/maintenance-tickets/${t.id}`)
                    }
                />
            </PluginRegion>
        </UnitLayout>
    );
}
