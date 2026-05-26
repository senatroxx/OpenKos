import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    DoorOpen,
    EllipsisVertical,
    Eye,
    Move,
    Pencil,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import LeaseFormSheet from '@/components/lease-form-sheet';
import MoveOutSheet from '@/components/move-out-sheet';
import MoveRoomSheet from '@/components/move-room-sheet';
import RoomDetailSheet from '@/components/room-detail-sheet';
import RoomFormSheet from '@/components/room-form-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import properties from '@/routes/properties';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type LeaseInfo = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    notes: string | null;
    tenant: TenantInfo | null;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
    description: string | null;
    base_price: string;
    size_sqm: string | null;
    capacity: number;
    status: string;
    notes: string | null;
    active_leases: number;
    leases: LeaseInfo[];
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
};

type PaginationLinks = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    property: Property;
    rooms: {
        data: Room[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    tenants: { id: number; name: string; phone: string }[];
    availableRooms: { id: number; name: string; property_id: number; property: { id: number; name: string; city: { name: string } | null } | null }[];
    sort?: string;
    direction?: string;
    search?: string;
    status?: string;
    per_page?: number;
};

const SORTABLE = ['name', 'floor', 'base_price', 'size_sqm', 'status', 'capacity'] as const;

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
    direction: currentDirection = 'asc',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
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
        tenant: { id: number; name: string; phone: string | null } | null;
        room: { id: number; name: string; property_id: number; property: { id: number; name: string; city: { name: string } | null } | null } | null;
    } | null>(null);
    const [moveOutOpen, setMoveOutOpen] = useState(false);

    const [searchValue, setSearchValue] = useState(currentSearch);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const routePrefix = properties.rooms.index.url(property);

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
            tenant: lease.tenant,
            room: {
                id: viewingRoom.id,
                name: viewingRoom.name,
                property_id: property.id,
                property: {
                    id: property.id,
                    name: property.name,
                    city: property.city ? { name: property.city } : null,
                },
            },
        });
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function destroy(room: Room) {
        if (confirm('Are you sure you want to delete this room?')) {
            router.delete(properties.rooms.destroy.url({ property: property.id, room: room.id }));
        }
    }

    function applyFilters(overrides: Record<string, string>) {
        const params: Record<string, string> = {
            search: searchValue,
            status: currentStatus,
            sort: currentSort,
            direction: currentDirection,
            per_page: String(currentPerPage),
            ...overrides,
        };

        Object.keys(params).forEach((key) => {
            if (!params[key]) {
                delete params[key];
            }
        });

        router.get(routePrefix, params, {
            preserveState: true,
            replace: true,
        });
    }

    function setStatusFilter(status: string) {
        applyFilters({ status, page: '' });
    }

    function handleSearchChange(value: string) {
        setSearchValue(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            const params: Record<string, string> = {
                sort: currentSort,
                direction: currentDirection,
                per_page: String(currentPerPage),
            };

            if (value) {
                params.search = value;
            }

            if (currentStatus) {
                params.status = currentStatus;
            }

            router.get(routePrefix, params, {
                preserveState: true,
                replace: true,
            });
        }, 300);
    }

    function toggleSort(column: string) {
        const direction =
            currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';

        applyFilters({ sort: column, direction, page: '' });
    }

    function goToPage(page: number) {
        applyFilters({ page: String(page) });
    }

    function SortIcon({ column }: { column: string }) {
        if (currentSort !== column) {
            return <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />;
        }

        return currentDirection === 'asc' ? (
            <ChevronUp className="ml-1 inline size-3.5" />
        ) : (
            <ChevronDown className="ml-1 inline size-3.5" />
        );
    }

    function getFilteredRoomsForMove(currentRoomId: number): { id: number; name: string }[] {
        return _availableRooms.filter((r) => r.id !== currentRoomId);
    }

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

                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or floor..."
                            className="pl-9"
                            value={searchValue}
                            onChange={(e) => handleSearchChange(e.target.value)}
                        />
                        {searchValue && (
                            <button
                                onClick={() => {
                                    if (debounceRef.current) {
                                        clearTimeout(debounceRef.current);
                                    }

                                    setSearchValue('');
                                    applyFilters({ search: '', page: '' });
                                }}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>

                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        {(['', 'available', 'occupied', 'maintenance', 'unavailable'] as const).map(
                            (value) => (
                                <Button
                                    key={value}
                                    variant={currentStatus === value ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setStatusFilter(value)}
                                >
                                    {value === '' ? 'All' : STATUS_LABELS[value]}
                                </Button>
                            ),
                        )}
                    </div>
                </div>

                {data.data.length === 0 ? (
                    <EmptyState
                        message="No rooms yet."
                        createLabel="Create your first room"
                        onCreate={openCreate}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    {SORTABLE.map((col) => (
                                        <th
                                            key={col}
                                            className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
                                            onClick={() => toggleSort(col)}
                                        >
                                            {col === 'name'
                                                ? 'Name'
                                                : col === 'floor'
                                                  ? 'Floor'
                                                  : col === 'base_price'
                                                    ? 'Price'
                                                    : col === 'size_sqm'
                                                      ? 'Size'
                                                      : col === 'status'
                                                        ? 'Status'
                                                        : 'Capacity'}
                                            <SortIcon column={col} />
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 font-medium">Tenant</th>
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((room) => {
                                    const activeLease = room.leases?.[0];
                                    const hasActiveLease = room.active_leases > 0 && activeLease;

                                    return (
                                        <tr
                                            key={room.id}
                                            className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                            onClick={() => openDetail(room)}
                                        >
                                            <td className="px-4 py-3 font-medium">{room.name}</td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {room.floor ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatPrice(room.base_price)}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                                {room.size_sqm ? `${room.size_sqm} m²` : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    className={`${STATUS_COLORS[room.status] ?? 'bg-gray-400'} text-white`}
                                                >
                                                    {STATUS_LABELS[room.status] ?? room.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">{room.capacity}</td>
                                            <td className="px-4 py-3">
                                                {hasActiveLease ? (
                                                    <span className="text-sm">
                                                        {activeLease.tenant?.name ?? 'Occupied'}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
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
                                                            onClick={() => openDetail(room)}
                                                        >
                                                            <Eye className="size-4" />
                                                            View
                                                        </DropdownMenuItem>
                                                        {hasActiveLease && (
                                                            <>
                                                                <DropdownMenuItem
                                                                    onClick={() => {
                                                                        setViewingRoom(room);
                                                                        setDetailOpen(false);
                                                                        const lease =
                                                                            room.leases?.[0];

                                                                        if (lease) {
                                                                            setMoveFromRoom(
                                                                                room,
                                                                            );
                                                                            setMoveLease(lease);
                                                                            setMoveOpen(true);
                                                                        }
                                                                    }}
                                                                >
                                                                    <Move className="size-4" />
                                                                    Move Room
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator />
                                                            </>
                                                        )}
                                                        {!hasActiveLease && (
                                                            <>
                                                                <DropdownMenuItem
                                                                    onClick={() => {
                                                                        setAssignRoom(room);
                                                                        setLeaseFormOpen(true);
                                                                    }}
                                                                >
                                                                    <DoorOpen className="size-4" />
                                                                    Assign Tenant
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator />
                                                            </>
                                                        )}
                                                        <DropdownMenuItem
                                                            onClick={() => openEdit(room)}
                                                        >
                                                            <Pencil className="size-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() => destroy(room)}
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                            <div className="flex items-center gap-4">
                                <p className="text-muted-foreground">
                                    Showing {data.from} to {data.to} of {data.total} rooms
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-xs">Per page</span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        value={currentPerPage}
                                        onChange={(e) =>
                                            applyFilters({
                                                per_page: e.target.value,
                                                page: '',
                                            })
                                        }
                                    >
                                        {[10, 15, 25, 50].map((n) => (
                                            <option key={n} value={n}>
                                                {n}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={data.current_page === 1}
                                    onClick={() => goToPage(data.current_page - 1)}
                                >
                                    Previous
                                </Button>

                                {data.links
                                    .filter((link) => !isNaN(Number(link.label)))
                                    .map((link) => (
                                        <Button
                                            key={link.label}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => goToPage(Number(link.label))}
                                        >
                                            {link.label}
                                        </Button>
                                    ))}

                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={data.current_page === data.last_page}
                                    onClick={() => goToPage(data.current_page + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
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
