import { Head, router } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    ExternalLink,
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
    MoveUnitSheet,
    UnitDetailSheet,
    UnitFormSheet,
} from '@/components/features';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { PropertyLayout } from '@/pages/properties/layout';
import properties from '@/routes/properties';
import type {
    LeaseInfo,
    PaginatedData,
    Property,
    Unit,
    TableMeta,
} from '@/types';

type PageProps = {
    property: Property;
    units: PaginatedData<Unit>;
    tenants: { id: number; name: string; phone: string }[];
    availableUnits: {
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
    units: data,
    availableUnits: _availableUnits,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<Unit | null>(null);

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingUnit, setViewingUnit] = useState<Unit | null>(null);

    const [leaseFormOpen, setLeaseFormOpen] = useState(false);
    const [assignUnit, setAssignUnit] = useState<Unit | null>(null);

    const [moveOpen, setMoveOpen] = useState(false);
    const [moveLease, setMoveLease] = useState<LeaseInfo | null>(null);
    const [moveFromUnit, setMoveFromUnit] = useState<Unit | null>(null);

    const [moveOutLeaseData, setMoveOutLeaseData] = useState<{
        id: number;
        tenants: { id: number; name: string; phone: string | null }[];
        primary_tenant: {
            id: number;
            name: string;
            phone: string | null;
        } | null;
        unit: {
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

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [unitToDelete, setUnitToDelete] = useState<Unit | null>(null);

    const table = useTable({
        routeFn: () => ({ url: properties.units.index.url(property) }),
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
        setEditingUnit(null);
        setDialogOpen(true);
    }

    function openEdit(unit: Unit) {
        setEditingUnit(unit);
        setDialogOpen(true);
    }

    function openDetail(unit: Unit) {
        setViewingUnit(unit);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingUnit) {
            return;
        }

        setEditingUnit(viewingUnit);
        setDialogOpen(true);
    }

    function openAssignTenant() {
        if (!viewingUnit) {
            return;
        }

        setAssignUnit(viewingUnit);
        setDetailOpen(false);
        setLeaseFormOpen(true);
    }

    function openMoveUnit() {
        if (!viewingUnit) {
            return;
        }

        const lease = viewingUnit.leases?.[0];

        if (lease) {
            setMoveFromUnit(viewingUnit);
            setMoveLease(lease);
            setDetailOpen(false);
            setMoveOpen(true);
        }
    }

    function openMoveOut() {
        if (!viewingUnit) {
            return;
        }

        const lease = viewingUnit.leases?.[0];

        if (!lease) {
            return;
        }

        setMoveOutLeaseData({
            id: lease.id,
            tenants: lease.tenants ?? [],
            primary_tenant: lease.primary_tenant ?? null,
            unit: {
                id: viewingUnit.id,
                name: viewingUnit.name,
                property_id: property.id,
                property: {
                    id: property.id,
                    name: property.name,
                    city:
                        property.city && typeof property.city === 'string'
                            ? { name: property.city }
                            : null,
                },
            },
        });
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function confirmDelete(unit: Unit) {
        setUnitToDelete(unit);
        setDeleteDialogOpen(true);
    }

    function destroy() {
        if (!unitToDelete) {
            return;
        }

        router.delete(
            properties.units.destroy.url({
                property: property.slug,
                unit: unitToDelete.slug,
            }),
        );
        setDeleteDialogOpen(false);
    }

    function getFilteredUnitsForMove(
        currentUnitId: number,
    ): (typeof _availableUnits)[number][] {
        return _availableUnits.filter((r) => r.id !== currentUnitId);
    }

    const columns: TableColumn<Unit>[] = [
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
                    <span className="text-sm text-muted-foreground">—</span>
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
                                onClick={() =>
                                    router.get(
                                        properties.units.show.url({
                                            property: property.slug,
                                            unit: r.slug,
                                        }),
                                    )
                                }
                            >
                                <ExternalLink className="size-4" />
                                Open Workspace
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => openDetail(r)}>
                                <Eye className="size-4" />
                                View
                            </DropdownMenuItem>
                            {r.capacity > occupants.length && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setAssignUnit(r);
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
                                        setViewingUnit(r);
                                        setDetailOpen(false);
                                        const lease = r.leases?.[0];

                                        if (lease) {
                                            setMoveFromUnit(r);
                                            setMoveLease(lease);
                                            setMoveOpen(true);
                                        }
                                    }}
                                >
                                    <Move className="size-4" />
                                    Move Unit
                                </DropdownMenuItem>
                            )}
                            {(r.capacity > occupants.length ||
                                hasActiveLease) && <DropdownMenuSeparator />}
                            <DropdownMenuItem onClick={() => openEdit(r)}>
                                <Pencil className="size-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => confirmDelete(r)}
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
        <PropertyLayout property={property} activeTab="units">
            <Head title={`Units - ${property.name}`} />

            <div className="flex flex-col gap-4">
                <div className="flex items-center justify-end">
                    <Button onClick={openCreate}>New Unit</Button>
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
                    noun="units"
                    empty={{
                        message: 'No units yet.',
                        createLabel: 'Create your first unit',
                        onCreate: openCreate,
                    }}
                />
            </div>

            <UnitDetailSheet
                unit={viewingUnit}
                property={property}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onAssignTenant={openAssignTenant}
                onMoveOut={openMoveOut}
                onMoveUnit={openMoveUnit}
            />

            <UnitFormSheet
                key={editingUnit?.id ?? 'new'}
                unit={editingUnit}
                property={property}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignUnit && (
                <LeaseFormSheet
                    unit={assignUnit}
                    property={property}
                    open={leaseFormOpen}
                    onOpenChange={setLeaseFormOpen}
                />
            )}

            {moveLease && moveFromUnit && (
                <MoveUnitSheet
                    property={property}
                    currentUnit={moveFromUnit}
                    availableUnits={getFilteredUnitsForMove(moveFromUnit.id)}
                    lease={moveLease}
                    open={moveOpen}
                    onOpenChange={setMoveOpen}
                />
            )}

            <MoveOutSheet
                lease={moveOutLeaseData}
                availableUnits={_availableUnits}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete unit</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <span className="font-medium">
                                {unitToDelete?.name}
                            </span>
                            ? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={destroy}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PropertyLayout>
    );
}
