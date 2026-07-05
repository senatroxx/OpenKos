import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    Eye,
    LogOut,
    EllipsisVertical,
    Pencil,
    RefreshCw,
    Building2,
    Banknote,
    AlertTriangle,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    LeaseDetailSheet,
    LeaseEditSheet,
    MoveOutSheet,
    RenewLeaseSheet,
} from '@/components/features';
import { Heading } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import leases from '@/routes/leases';
import units from '@/routes/properties/units';
import type { Lease, PaginatedData, TableMeta } from '@/types';

type AvailableUnit = {
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
    availableRooms: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    properties?: string;
    per_page?: number;
    payment_status?: string;
    table: TableMeta;
    stats?: {
        active_leases: number;
        collected_this_month: number;
        overdue_amount: number;
    };
};

const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day',
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
    payment_status: currentPaymentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
    stats,
}: PageProps) {
    const [detailLease, setDetailLease] = useState<Lease | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [renewOpen, setRenewOpen] = useState(false);

    const table = useTable({
        routeFn: () => leases.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
            properties: currentProperties,
            payment_status: currentPaymentStatus,
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
            key: 'reference',
            label: 'Reference',
            className: 'font-mono text-xs',
            render: (lease) => lease.reference ?? '\u2014',
        },
        {
            key: '_tenant',
            label: 'Tenant',
            className: 'font-medium',
            render: (lease) => (
                <div>
                    <p className="font-medium">
                        {(lease.tenants ?? []).length > 0
                            ? lease.tenants.map((t) => t.name).join(', ') ||
                              lease.tenants[0]?.name
                            : (lease.primary_tenant?.name ?? '\u2014')}
                    </p>
                    {lease.unit && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            <Link
                                href={units.index({
                                    property: lease.unit.property!.slug,
                                })}
                                onClick={(e: React.MouseEvent) =>
                                    e.stopPropagation()
                                }
                                className="text-blue-600 hover:underline"
                            >
                                {lease.unit.name}
                            </Link>
                            {' · '}
                            {lease.unit.property?.name ?? '\u2014'}
                        </p>
                    )}
                </div>
            ),
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
            key: 'rent_due_day',
            label: 'Due',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) =>
                lease.status === 'active'
                    ? (DUE_DAY_LABELS[lease.rent_due_day] ??
                      `${lease.rent_due_day}th`)
                    : '—',
        },
        {
            key: 'payment_status',
            label: 'Payment',
            render: (lease) =>
                lease.status === 'active' && lease.payment_status ? (
                    <Badge
                        className={
                            lease.payment_status === 'paid'
                                ? 'bg-green-600 text-white'
                                : 'bg-red-600 text-white'
                        }
                    >
                        {lease.payment_status === 'paid' ? 'Paid' : 'Overdue'}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
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
                        <DropdownMenuItem
                            onClick={() => router.get(leases.show.url(lease))}
                        >
                            <ExternalLink className="size-4" />
                            Open Workspace
                        </DropdownMenuItem>
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
                            <>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setDetailLease(lease);
                                        setDetailOpen(false);
                                        setRenewOpen(true);
                                    }}
                                >
                                    <RefreshCw className="size-4" />
                                    Renew
                                </DropdownMenuItem>
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
                            </>
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

                {stats && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-4">
                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <Building2 className="size-10 shrink-0 text-blue-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Active Leases
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {stats.active_leases}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <Banknote className="size-10 shrink-0 text-green-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Collected This Month
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {formatPrice(
                                            String(stats.collected_this_month),
                                        )}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <AlertTriangle className="size-10 shrink-0 text-red-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Overdue
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {formatPrice(
                                            String(stats.overdue_amount),
                                        )}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

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
                            placeholder="Search tenant, unit, property..."
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
                              unit: detailLease.unit,
                          }
                        : null
                }
                availableRooms={_availableRooms}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />

            <RenewLeaseSheet
                lease={detailLease}
                open={renewOpen}
                onOpenChange={setRenewOpen}
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
