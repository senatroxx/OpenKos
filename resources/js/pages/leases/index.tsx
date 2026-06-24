import { Head, Link } from '@inertiajs/react';
import {
    Eye,
    LogOut,
    EllipsisVertical,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import Heading from '@/components/heading';
import LeaseDetailSheet from '@/components/lease-detail-sheet';
import LeaseEditSheet from '@/components/lease-edit-sheet';
import MoveOutSheet from '@/components/move-out-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import leases from '@/routes/leases';
import type { PaginatedData, TableMeta } from '@/types';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
    pivot?: {
        is_primary: boolean;
    };
};

type RoomInfo = {
    id: number;
    name: string;
    property_id: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_interval: number;
    billing_unit: string;
    monthly_equivalent: string;
    billing_label: string;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    termination_date: string | null;
    termination_reason: string | null;
    notes: string | null;
    created_at: string;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    room: RoomInfo | null;
};

type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    capacity: number;
    occupied_count: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

type PageProps = {
    leases: PaginatedData<Lease>;
    availableRooms: AvailableRoom[];
    sort?: string;
    search?: string;
    status?: string;
    properties?: string;
    per_page?: number;
    table: TableMeta;
};

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-blue-600',
    terminated: 'bg-gray-400',
};

function formatPrice(cents: string | null): string {
    if (!cents) {
        return '\u2014';
    }

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(date: string | null): string {
    if (!date) {
        return '\u2014';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Index({
    leases: data,
    availableRooms: _availableRooms,
    sort: currentSort = 'status,-start_date',
    search: currentSearch = '',
    status: currentStatus = '',
    properties: currentProperties = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [detailLease, setDetailLease] = useState<Lease | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [moveOutOpen, setMoveOutOpen] = useState(false);

    const table = useTable({
        routeFn: () => leases.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
            properties: currentProperties,
        },
        defaults: {
            sort: 'status,-start_date',
            per_page: '15',
        },
    });

    function openDetail(lease: Lease) {
        setDetailLease(lease);
        setDetailOpen(true);
    }

    function openMoveOutFromDetail() {
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openEditFromDetail() {
        setDetailOpen(false);
        setEditOpen(true);
    }

    const columns: TableColumn<Lease>[] = [
        {
            key: '_tenant',
            label: 'Tenant',
            className: 'font-medium',
            render: (lease) =>
                (lease.tenants ?? []).length > 0
                    ? (lease.tenants.map((t) => t.name).join(', ') ||
                          lease.tenants[0]?.name)
                    : lease.primary_tenant?.name ?? '\u2014',
        },
        {
            key: '_room',
            label: 'Room',
            render: (lease) =>
                lease.room ? (
                    <Link
                        href={`/properties/${lease.room.property_id}/rooms`}
                        className="text-blue-600 hover:underline"
                        onClick={(e: React.MouseEvent) => e.stopPropagation()}
                    >
                        {lease.room.name ?? '\u2014'}
                    </Link>
                ) : (
                    '\u2014'
                ),
        },
        {
            key: '_property',
            label: 'Property',
            className: 'text-muted-foreground',
            render: (lease) => lease.room?.property?.name ?? '\u2014',
        },
        {
            key: 'start_date',
            label: 'Start',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) => formatDate(lease.start_date),
        },
        {
            key: 'end_date',
            label: 'End',
            sortable: true,
            className: 'text-muted-foreground tabular-nums',
            render: (lease) => formatDate(lease.end_date),
        },
        {
            key: 'rent_amount',
            label: 'Rent',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) =>
                `${formatPrice(lease.rent_amount)} ${lease.billing_label ?? ''}`,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (lease) => (
                <Badge
                    className={`${STATUS_COLORS[lease.status] ?? 'bg-gray-400'} text-white`}
                >
                    {lease.status === 'active' ? 'Active' : 'Terminated'}
                </Badge>
            ),
        },
        {
            key: '_actions',
            label: '',
            render: (lease) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(e: React.MouseEvent) => e.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(e: React.MouseEvent) => e.stopPropagation()}
                    >
                        <DropdownMenuItem onClick={() => openDetail(lease)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                setDetailLease(lease);
                                setDetailOpen(false);
                                setEditOpen(true);
                            }}
                        >
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        {lease.status === 'active' && (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => {
                                    setDetailLease(lease);
                                    setDetailOpen(false);
                                    setMoveOutOpen(true);
                                }}
                            >
                                <LogOut className="size-4" />
                                Move Out
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Leases" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leases"
                        description="View all leases across properties"
                    />
                </div>

                <FilterBar
                    filters={tableMeta.filters}
                    activeFilters={table.activeFilters}
                    activeFilterCount={table.activeFilterCount}
                    onToggleOption={table.toggleFilterOption}
                    onClearAll={table.clearAllFilters}
                    searchInput={
                        <SearchInput
                            value={table.searchValue}
                            onChange={table.onSearchChange}
                            onClear={table.clearSearch}
                            placeholder="Search tenant, room, property..."
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={openDetail}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun="leases"
                    empty={{
                        message: 'No leases found.',
                    }}
                />
            </div>

            <LeaseDetailSheet
                lease={detailLease}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onMoveOut={
                    detailLease?.status === 'active'
                        ? openMoveOutFromDetail
                        : undefined
                }
                onEdit={detailLease ? openEditFromDetail : undefined}
            />

            <LeaseEditSheet
                lease={detailLease}
                open={editOpen}
                onOpenChange={setEditOpen}
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                              id: detailLease.id,
                              tenants: detailLease.tenants,
                              primary_tenant: detailLease.primary_tenant,
                              room: detailLease.room,
                          }
                        : null
                }
                availableRooms={_availableRooms}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Leases',
            href: leases.index(),
        },
    ],
};
