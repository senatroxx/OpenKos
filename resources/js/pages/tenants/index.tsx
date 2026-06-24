import { Head, router } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    Eye,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import AssignRoomSheet from '@/components/assign-room-sheet';
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
import { useTable } from '@/hooks/use-table';
import tenants from '@/routes/tenants';
import type { PaginatedData, TableMeta } from '@/types';

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

type PageProps = {
    tenants: PaginatedData<Tenant>;
    availableRooms: AvailableRoom[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export default function Index({
    tenants: data,
    availableRooms: _availableRooms,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTenant, setEditingTenant] = useState<Tenant | null>(null);

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingTenant, setViewingTenant] = useState<Tenant | null>(null);

    const [assignRoomOpen, setAssignRoomOpen] = useState(false);
    const [assignTenant, setAssignTenant] = useState<Tenant | null>(null);

    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [moveOutTenant, setMoveOutTenant] = useState<Tenant | null>(null);

    const table = useTable({
        routeFn: () => tenants.index(),
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

    const columns: TableColumn<Tenant>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'phone',
            label: 'Phone',
            sortable: true,
            className: 'text-muted-foreground',
            render: (t) => t.phone ?? '\u2014',
        },
        {
            key: '_lease',
            label: 'Lease',
            render: (t) =>
                t.active_leases_count > 0 ? (
                    <Badge className="bg-green-600">Active</Badge>
                ) : (
                    <Badge variant="outline">None</Badge>
                ),
        },
        {
            key: '_status',
            label: 'Status',
            render: (t) =>
                t.deleted_at ? (
                    <Badge variant="secondary">Archived</Badge>
                ) : t.is_active ? (
                    <Badge variant="default" className="bg-green-600">
                        Active
                    </Badge>
                ) : (
                    <Badge
                        variant="outline"
                        className="border-amber-300 text-amber-600"
                    >
                        Inactive
                    </Badge>
                ),
        },
        {
            key: '_actions',
            label: '',
            render: (t) => (
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
                        <DropdownMenuItem onClick={() => openDetail(t)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        {!t.deleted_at && t.active_leases_count === 0 && (
                            <DropdownMenuItem
                                onClick={() => {
                                    setAssignTenant(t);
                                    setAssignRoomOpen(true);
                                }}
                            >
                                <DoorOpen className="size-4" />
                                Assign to Room
                            </DropdownMenuItem>
                        )}
                        {!t.deleted_at && (
                            <DropdownMenuItem onClick={() => openEdit(t)}>
                                <Pencil className="size-4" />
                                Edit
                            </DropdownMenuItem>
                        )}
                        {!t.deleted_at && (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => archive(t)}
                            >
                                <Trash2 className="size-4" />
                                Archive
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

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
                            placeholder="Search by name, phone, or ID card..."
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
                    noun="tenants"
                    empty={{
                        message: 'No tenants yet.',
                        createLabel: 'Create your first tenant',
                        onCreate: openCreate,
                    }}
                />
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
