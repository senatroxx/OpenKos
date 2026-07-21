import { Head, Link, router } from '@inertiajs/react';
import { Eye, EllipsisVertical } from 'lucide-react';
import type { TableColumn } from '@/components/data-table';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate, formatPrice } from '@/lib/formatters';
import { index, show } from '@/routes/portal/lease';
import type { Lease, PaginatedData, TableMeta } from '@/types';

const columns: TableColumn<Lease>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (lease) => lease.reference ?? `#${lease.id}`,
    },
    {
        key: '_unit',
        label: 'Unit',
        className: 'font-medium',
        render: (lease) =>
            lease.unit
                ? `${lease.unit.name}${lease.unit.property ? ` - ${lease.unit.property.name}` : ''}`
                : '—',
    },
    {
        key: 'start_date',
        label: 'Start',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (lease) => formatDate(lease.start_date),
    },
    {
        key: 'end_date',
        label: 'End',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (lease) => (lease.end_date ? formatDate(lease.end_date) : 'Ongoing'),
    },
    {
        key: 'rent_amount',
        label: 'Rent',
        sortable: true,
        className: 'tabular-nums',
        render: (lease) => formatPrice(lease.rent_amount),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (lease) => <StatusBadge domain="lease" value={lease.status} />,
    },
        {
            key: '_actions',
            label: '',
            render: (lease) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild onClick={(event) => event.stopPropagation()}>
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" onClick={(event) => event.stopPropagation()}>
                        <DropdownMenuItem asChild>
                            <Link href={show(lease)}>
                                <Eye className="size-4" />
                                View details
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
];

export default function LeaseIndex({
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
}: {
    leases: PaginatedData<Lease>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <>
            <Head title="Leases" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Leases</h1>
                    <p className="text-sm text-muted-foreground">
                        Current and previous stays
                    </p>
                </div>

                <WorkspaceTable
                    url={index.url()}
                    noun="leases"
                    rows={leases}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status }}
                    defaultSort="-start_date"
                    searchPlaceholder="Search by reference..."
                    emptyMessage="No leases found."
                    onRowClick={(lease) => router.get(show.url(lease))}
                />
            </div>
        </>
    );
}

LeaseIndex.layout = {
    breadcrumbs: [{ title: 'Leases', href: index() }],
};
