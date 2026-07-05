import { Head, router } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    ExternalLink,
    Eye,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    AssignUnitSheet,
    MoveOutSheet,
    TenantDetailSheet,
    TenantDocumentsSheet,
    TenantFormSheet,
} from '@/components/features';
import { Heading } from '@/components/shared';
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
import type { AvailableUnit, PaginatedData, UnitWithProperty, TableMeta, TenantInfo } from '@/types';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    unit: UnitWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
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
    availableUnits: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export default function Index({
    tenants: data,
    availableUnits: _availableRooms,
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

    const [assignUnitOpen, setAssignUnitOpen] = useState(false);
    const [assignTenant, setAssignTenant] = useState<Tenant | null>(null);

    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [moveOutTenant, setMoveOutTenant] = useState<Tenant | null>(null);

    const [documentsOpen, setDocumentsOpen] = useState(false);
    const [documentsTenant, setDocumentsTenant] = useState<Tenant | null>(null);

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

    function openAssignUnit() {
        if (!viewingTenant) {
            return;
        }

        setAssignTenant(viewingTenant);
        setDetailOpen(false);
        setAssignUnitOpen(true);
    }

    function openMoveOut() {
        if (!viewingTenant) {
            return;
        }

        setMoveOutTenant(viewingTenant);
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openDocuments() {
        if (!viewingTenant) {
            return;
        }

        setDocumentsTenant(viewingTenant);
        setDetailOpen(false);
        setDocumentsOpen(true);
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
                        <DropdownMenuItem onClick={() => router.get(tenants.show.url(t))}>
                            <ExternalLink className="size-4" />
                            Open Workspace
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(t)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        {!t.deleted_at && t.active_leases_count === 0 && (
                            <DropdownMenuItem
                                onClick={() => {
                                    setAssignTenant(t);
                                    setAssignUnitOpen(true);
                                }}
                            >
                                <DoorOpen className="size-4" />
                                Assign to Unit
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
                onAssignToUnit={openAssignUnit}
                onMoveOut={openMoveOut}
                onDocuments={openDocuments}
            />

            <TenantFormSheet
                tenant={editingTenant}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignTenant && (
                <AssignUnitSheet
                    tenant={assignTenant}
                    availableUnits={_availableRooms}
                    open={assignUnitOpen}
                    onOpenChange={setAssignUnitOpen}
                />
            )}

            <TenantDocumentsSheet
                tenant={documentsTenant}
                open={documentsOpen}
                onOpenChange={setDocumentsOpen}
            />

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
                              unit: moveOutTenant.leases?.[0]?.unit ?? null,
                          }
                        : null
                }
                availableUnits={_availableRooms}
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
