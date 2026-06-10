import { Head, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    DoorOpen,
    EllipsisVertical,
    Eye,
    Pencil,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import AssignRoomSheet from '@/components/assign-room-sheet';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import MoveOutSheet from '@/components/move-out-sheet';
import TenantDetailSheet from '@/components/tenant-detail-sheet';
import TenantFormSheet from '@/components/tenant-form-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import tenants from '@/routes/tenants';

type Property = {
    id: number;
    name: string;
    city: { name: string } | null;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
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

type RoomWithProperty = Room & {
    property_id: number;
    property: Property | null;
};

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
    pivot?: { is_primary: boolean };
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    room: RoomWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
    deleted_at: string | null;
    active_leases_count: number;
    leases: Lease[];
};

type PaginationLinks = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    tenants: {
        data: Tenant[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    availableRooms: AvailableRoom[];
    sort?: string;
    direction?: string;
    search?: string;
    status?: string;
    per_page?: number;
};

const SORTABLE = ['name', 'phone'] as const;

export default function Index({
    tenants: data,
    availableRooms: _availableRooms,
    sort: currentSort = 'name',
    direction: currentDirection = 'asc',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTenant, setEditingTenant] = useState<Tenant | null>(null);

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingTenant, setViewingTenant] = useState<Tenant | null>(null);

    const [assignRoomOpen, setAssignRoomOpen] = useState(false);
    const [assignTenant, setAssignTenant] = useState<Tenant | null>(null);

    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [moveOutTenant, setMoveOutTenant] = useState<Tenant | null>(null);

    const [searchValue, setSearchValue] = useState(currentSearch);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    function openCreate() {
        setEditingTenant(null);
        setDialogOpen(true);
    }

    function openEdit(tenant: Tenant) {
        setEditingTenant(tenant);
        setDialogOpen(true);
    }

    function openDetail(tenant: Tenant) {
        setViewingTenant(tenant);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingTenant) {
            return;
        }

        setEditingTenant(viewingTenant);
        setDialogOpen(true);
    }

    function openAssignRoom() {
        if (!viewingTenant) {
            return;
        }

        setAssignTenant(viewingTenant);
        setDetailOpen(false);
        setAssignRoomOpen(true);
    }

    function openMoveOut() {
        if (!viewingTenant) {
            return;
        }

        setMoveOutTenant(viewingTenant);
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function archive(tenant: Tenant) {
        if (confirm('Are you sure you want to archive this tenant?')) {
            router.delete(tenants.destroy.url(tenant));
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

        router.get(tenants.index(), params, {
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

            router.get(tenants.index(), params, {
                preserveState: true,
                replace: true,
            });
        }, 300);
    }

    function toggleSort(column: string) {
        const direction =
            currentSort === column && currentDirection === 'asc'
                ? 'desc'
                : 'asc';

        applyFilters({ sort: column, direction, page: '' });
    }

    function goToPage(page: number) {
        applyFilters({ page: String(page) });
    }

    function SortIcon({ column }: { column: string }) {
        if (currentSort !== column) {
            return (
                <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />
            );
        }

        return currentDirection === 'asc' ? (
            <ChevronUp className="ml-1 inline size-3.5" />
        ) : (
            <ChevronDown className="ml-1 inline size-3.5" />
        );
    }

    return (
        <>
            <Head title="Tenants" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Tenants"
                        description="Manage your tenants"
                    />

                    <Button onClick={openCreate}>New Tenant</Button>
                </div>

                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name, phone, or ID card..."
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
                                    applyFilters({
                                        search: '',
                                        page: '',
                                    });
                                }}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>

                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        {(['', 'active', 'inactive', 'archived'] as const).map(
                            (value) => (
                                <Button
                                    key={value}
                                    variant={
                                        currentStatus === value
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    size="sm"
                                    onClick={() => setStatusFilter(value)}
                                >
                                    {value === ''
                                        ? 'All'
                                        : value === 'active'
                                          ? 'Active'
                                          : value === 'inactive'
                                            ? 'Inactive'
                                            : 'Archived'}
                                </Button>
                            ),
                        )}
                    </div>
                </div>

                {data.data.length === 0 ? (
                    <EmptyState
                        message="No tenants yet."
                        createLabel="Create your first tenant"
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
                                            {col === 'name' ? 'Name' : 'Phone'}
                                            <SortIcon column={col} />
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 font-medium">
                                        Lease
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((tenant) => (
                                    <tr
                                        key={tenant.id}
                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                        onClick={() => openDetail(tenant)}
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {tenant.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {tenant.phone ?? '—'}
                                        </td>

                                        <td className="px-4 py-3">
                                            {tenant.active_leases_count > 0 ? (
                                                <Badge className="bg-green-600">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    None
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tenant.deleted_at ? (
                                                <Badge variant="secondary">
                                                    Archived
                                                </Badge>
                                            ) : tenant.is_active ? (
                                                <Badge
                                                    variant="default"
                                                    className="bg-green-600"
                                                >
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-amber-300 text-amber-600"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger
                                                    asChild
                                                    onClick={(
                                                        e: React.MouseEvent,
                                                    ) => e.stopPropagation()}
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
                                                    onClick={(
                                                        e: React.MouseEvent,
                                                    ) => e.stopPropagation()}
                                                >
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            openDetail(tenant)
                                                        }
                                                    >
                                                        <Eye className="size-4" />
                                                        View
                                                    </DropdownMenuItem>
                                                    {!tenant.deleted_at &&
                                                        tenant.active_leases_count ===
                                                            0 && (
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    setAssignTenant(
                                                                        tenant,
                                                                    );
                                                                    setAssignRoomOpen(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <DoorOpen className="size-4" />
                                                                Assign to Room
                                                            </DropdownMenuItem>
                                                        )}
                                                    {!tenant.deleted_at && (
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                openEdit(tenant)
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                    )}
                                                    {!tenant.deleted_at && (
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                archive(tenant)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Archive
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                            <div className="flex items-center gap-4">
                                <p className="text-muted-foreground">
                                    Showing {data.from} to {data.to} of{' '}
                                    {data.total} tenants
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Per page
                                    </span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
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
                                    onClick={() =>
                                        goToPage(data.current_page - 1)
                                    }
                                >
                                    Previous
                                </Button>

                                {data.links
                                    .filter(
                                        (link) => !isNaN(Number(link.label)),
                                    )
                                    .map((link) => (
                                        <Button
                                            key={link.label}
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                goToPage(Number(link.label))
                                            }
                                        >
                                            {link.label}
                                        </Button>
                                    ))}

                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        data.current_page === data.last_page
                                    }
                                    onClick={() =>
                                        goToPage(data.current_page + 1)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <TenantDetailSheet
                tenant={viewingTenant}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onAssignToRoom={openAssignRoom}
                onMoveOut={openMoveOut}
            />

            <TenantFormSheet
                tenant={editingTenant}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignTenant && (
                <AssignRoomSheet
                    tenant={assignTenant}
                    availableRooms={_availableRooms}
                    open={assignRoomOpen}
                    onOpenChange={setAssignRoomOpen}
                />
            )}

            <MoveOutSheet
                lease={
                    moveOutTenant
                        ? {
                              id: moveOutTenant.leases?.[0]?.id ?? 0,
                              tenants: [{
                                  id: moveOutTenant.id,
                                  name: moveOutTenant.name,
                                  phone: moveOutTenant.phone,
                                  pivot: { is_primary: true },
                              }],
                              primary_tenant: {
                                  id: moveOutTenant.id,
                                  name: moveOutTenant.name,
                                  phone: moveOutTenant.phone,
                              },
                              room: moveOutTenant.leases?.[0]?.room ?? null,
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
            title: 'Tenants',
            href: tenants.index(),
        },
    ],
};
