import { Head, Link, router } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    Eye,
    Move,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    LeaseFormSheet,
    MoveOutSheet,
    MoveRoomSheet,
    RoomDetailSheet,
    RoomFormSheet,
} from '@/components/features';
import { Heading } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import properties from '@/routes/properties';
import type { LeaseInfo, PaginatedData, Property, Room, TableMeta } from '@/types';

type PageProps = {
    property: Property;
    rooms: PaginatedData<Room>;
    tenants: { id: number; name: string; phone: string }[];
    availableRooms: {
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
    }[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

const STATUS_LABELS: Record<string, string> = {
    available: 'Available',
    occupied: 'Occupied',
    maintenance: 'Maintenance',
    unavailable: 'Unavailable',
};

const STATUS_COLORS: Record<string, string> = {
    available: 'bg-green-600',
    occupied: 'bg-blue-600',
    maintenance: 'bg-amber-500',
    unavailable: 'bg-gray-400',
};

function formatPrice(cents: string): string {
    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

export default function Index({
    property,
    rooms: data,
    availableRooms: _availableRooms,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRoom, setEditingRoom] = useState<Room | null>(null);

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingRoom, setViewingRoom] = useState<Room | null>(null);

    const [leaseFormOpen, setLeaseFormOpen] = useState(false);
    const [assignRoom, setAssignRoom] = useState<Room | null>(null);

    const [moveOpen, setMoveOpen] = useState(false);
    const [moveLease, setMoveLease] = useState<LeaseInfo | null>(null);
    const [moveFromRoom, setMoveFromRoom] = useState<Room | null>(null);

    const [moveOutLeaseData, setMoveOutLeaseData] = useState<{
        id: number;
        tenants: { id: number; name: string; phone: string | null }[];
        primary_tenant: { id: number; name: string; phone: string | null } | null;
        room: {
            id: number;
            name: string;
            property_id: number;
            property: {
                id: number;
                name: string;
                city: { name: string } | null;
            } | null;
        } | null;
    } | null>(null);
    const [moveOutOpen, setMoveOutOpen] = useState(false);

    const table = useTable({
        routeFn: () => ({ url: properties.rooms.index.url(property) }),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
        },
        defaults: {
            sort: 'name',
            per_page: '15',
        },
    });

    function openCreate() {
        setEditingRoom(null);
        setDialogOpen(true);
    }

    function openEdit(room: Room) {
        setEditingRoom(room);
        setDialogOpen(true);
    }

    function openDetail(room: Room) {
        setViewingRoom(room);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingRoom) {
            return;
        }

        setEditingRoom(viewingRoom);
        setDialogOpen(true);
    }

    function openAssignTenant() {
        if (!viewingRoom) {
            return;
        }

        setAssignRoom(viewingRoom);
        setDetailOpen(false);
        setLeaseFormOpen(true);
    }

    function openMoveRoom() {
        if (!viewingRoom) {
            return;
        }

        const lease = viewingRoom.leases?.[0];

        if (lease) {
            setMoveFromRoom(viewingRoom);
            setMoveLease(lease);
            setDetailOpen(false);
            setMoveOpen(true);
        }
    }

    function openMoveOut() {
        if (!viewingRoom) {
            return;
        }

        const lease = viewingRoom.leases?.[0];

        if (!lease) {
            return;
        }

        setMoveOutLeaseData({
            id: lease.id,
            tenants: lease.tenants ?? [],
            primary_tenant: lease.primary_tenant ?? null,
            room: {
                id: viewingRoom.id,
                name: viewingRoom.name,
                property_id: property.id,
                property: {
                    id: property.id,
                    name: property.name,
                    city: property.city && typeof property.city === 'string' ? { name: property.city } : null,
                },
            },
        });
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function destroy(room: Room) {
        if (confirm('Are you sure you want to delete this room?')) {
            router.delete(
                properties.rooms.destroy.url({
                    property: property.id,
                    room: room.id,
                }),
            );
        }
    }

    function getFilteredRoomsForMove(
        currentRoomId: number,
    ): (typeof _availableRooms)[number][] {
        return _availableRooms.filter((r) => r.id !== currentRoomId);
    }

    const columns: TableColumn<Room>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'floor',
            label: 'Floor',
            sortable: true,
            className: 'text-muted-foreground',
            render: (r) => r.floor ?? '\u2014',
        },
        {
            key: 'size_sqm',
            label: 'Size',
            sortable: true,
            className: 'text-muted-foreground tabular-nums',
            render: (r) => (r.size_sqm ? `${r.size_sqm} m\u00B2` : '\u2014'),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (r) => (
                <Badge
                    className={`${STATUS_COLORS[r.status] ?? 'bg-gray-400'} text-white`}
                >
                    {STATUS_LABELS[r.status] ?? r.status}
                </Badge>
            ),
        },
        {
            key: 'capacity',
            label: 'Capacity',
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: '_pricing',
            label: 'Pricing',
            className: 'tabular-nums',
            render: (r) =>
                r.active_rates?.[0]
                    ? formatPrice(r.active_rates[0].amount)
                    : '\u2014',
        },
        {
            key: '_tenant',
            label: 'Tenant',
            render: (r) => {
                const hasActiveLease = (r.active_leases ?? 0) > 0;
                const occupants = hasActiveLease
                    ? (r.leases ?? []).flatMap(
                          (l) =>
                              l.tenants ??
                              (l.primary_tenant ? [l.primary_tenant] : []),
                      )
                    : [];

                return hasActiveLease && occupants.length > 0 ? (
                    <div className="text-sm">
                        {occupants.map((t) => (
                            <div key={t.id}>{t.name}</div>
                        ))}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        \u2014
                    </span>
                );
            },
        },
        {
            key: '_actions',
            label: '',
            render: (r) => {
                const hasActiveLease = (r.active_leases ?? 0) > 0;
                const occupants = hasActiveLease
                    ? (r.leases ?? []).flatMap(
                          (l) =>
                              l.tenants ??
                              (l.primary_tenant ? [l.primary_tenant] : []),
                      )
                    : [];

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            asChild
                            onClick={(e: React.MouseEvent) =>
                                e.stopPropagation()
                            }
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                            >
                                <EllipsisVertical className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            onClick={(e: React.MouseEvent) =>
                                e.stopPropagation()
                            }
                        >
                            <DropdownMenuItem
                                onClick={() => openDetail(r)}
                            >
                                <Eye className="size-4" />
                                View
                            </DropdownMenuItem>
                            {r.capacity > occupants.length && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setAssignRoom(r);
                                        setLeaseFormOpen(true);
                                    }}
                                >
                                    <DoorOpen className="size-4" />
                                    Assign Tenant
                                    {r.capacity > 1 ? '(s)' : ''}
                                </DropdownMenuItem>
                            )}
                            {hasActiveLease && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setViewingRoom(r);
                                        setDetailOpen(false);
                                        const lease = r.leases?.[0];

                                        if (lease) {
                                            setMoveFromRoom(r);
                                            setMoveLease(lease);
                                            setMoveOpen(true);
                                        }
                                    }}
                                >
                                    <Move className="size-4" />
                                    Move Room
                                </DropdownMenuItem>
                            )}
                            {(r.capacity > occupants.length ||
                                hasActiveLease) && <DropdownMenuSeparator />}
                            <DropdownMenuItem
                                onClick={() => openEdit(r)}
                            >
                                <Pencil className="size-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => destroy(r)}
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <>
            <Head title={`Rooms - ${property.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Heading
                            title={property.name}
                            description="Manage rooms for this property"
                        />
                        <Link
                            href={properties.index()}
                            className="mt-1 inline-block text-xs text-muted-foreground hover:text-foreground"
                        >
                            &larr; Back to properties
                        </Link>
                    </div>

                    <Button onClick={openCreate}>New Room</Button>
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
                            placeholder="Search by name or floor..."
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
                    noun="rooms"
                    empty={{
                        message: 'No rooms yet.',
                        createLabel: 'Create your first room',
                        onCreate: openCreate,
                    }}
                />
            </div>

            <RoomDetailSheet
                room={viewingRoom}
                property={property}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onAssignTenant={openAssignTenant}
                onMoveOut={openMoveOut}
                onMoveRoom={openMoveRoom}
            />

            <RoomFormSheet
                key={editingRoom?.id ?? 'new'}
                room={editingRoom}
                property={property}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignRoom && (
                <LeaseFormSheet
                    room={assignRoom}
                    property={property}
                    open={leaseFormOpen}
                    onOpenChange={setLeaseFormOpen}
                />
            )}

            {moveLease && moveFromRoom && (
                <MoveRoomSheet
                    property={property}
                    currentRoom={moveFromRoom}
                    availableRooms={getFilteredRoomsForMove(moveFromRoom.id)}
                    lease={moveLease}
                    open={moveOpen}
                    onOpenChange={setMoveOpen}
                />
            )}

            <MoveOutSheet
                lease={moveOutLeaseData}
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
            title: 'Properties',
            href: properties.index(),
        },
        {
            title: 'Rooms',
        },
    ],
};
