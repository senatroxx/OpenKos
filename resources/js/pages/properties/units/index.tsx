import { Head, router, usePage } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    ExternalLink,
    Move,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    AssignTenantSheet,
    MoveUnitSheet,
    UnitFormSheet,
} from '@/components/features';
import { EntityTransferMenu } from '@/components/features/data-transfer/transfer-actions';
import { StatusBadge } from '@/components/shared/status-badge';
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
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { PropertyLayout } from '@/pages/properties/layout';
import { UnitTypeLayout } from '@/pages/properties/unit-types/layout';
import properties from '@/routes/properties';
import type {
    AuthPageProps,
    LeaseInfo,
    Unit,
    UnitsPageProps,
} from '@/types';

export default function Index({
    property,
    units: data,
    unitTypes,
    availableUnits: _availableUnits,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
    unitTypeWorkspace,
}: UnitsPageProps) {
    const { auth } = usePage<AuthPageProps>().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<Unit | null>(null);

    const [leaseFormOpen, setLeaseFormOpen] = useState(false);
    const [assignUnit, setAssignUnit] = useState<Unit | null>(null);

    const [moveOpen, setMoveOpen] = useState(false);
    const [moveLease, setMoveLease] = useState<LeaseInfo | null>(null);
    const [moveFromUnit, setMoveFromUnit] = useState<Unit | null>(null);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [unitToDelete, setUnitToDelete] = useState<Unit | null>(null);

    const table = useTable({
        routeFn: () => (unitTypeWorkspace
            ? { url: properties.unitTypes.units.url({ property, unitType: unitTypeWorkspace }) }
            : { url: properties.units.index.url(property) }),
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

    const currentEditingUnit = editingUnit
        ? (data.data.find((item) => item.id === editingUnit.id) ?? editingUnit)
        : null;

    function openCreate() {
        setEditingUnit(null);
        setDialogOpen(true);
    }

    function openEdit(unit: Unit) {
        setEditingUnit(unit);
        setDialogOpen(true);
    }

    function openMoveUnit(unit: Unit) {
        const lease = unit.leases?.[0];

        if (lease) {
            setMoveFromUnit(unit);
            setMoveLease(lease);
            setMoveOpen(true);
        }
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

    function restore(unit: Unit) {
        router.post(
            properties.units.restore.url({
                property: property.slug,
                unit: unit.slug,
            }),
        );
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
            key: 'unit_type',
            label: 'Unit Type',
            render: (r) => r.unit_type?.name ?? '—',
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
            render: (r) =>
                r.deleted_at ? (
                    <StatusBadge domain="unit" value="archived" />
                ) : (
                    <StatusBadge domain="unit" value={r.status} />
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
                    ? formatPrice(
                          r.active_rates[0].amount,
                          r.active_rates[0].currency,
                      )
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
                            {!r.deleted_at && (
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
                                    {t('Open Workspace')}
                                </DropdownMenuItem>
                            )}
                            {!r.deleted_at && r.capacity > occupants.length && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setAssignUnit(r);
                                        setLeaseFormOpen(true);
                                    }}
                                >
                                    <DoorOpen className="size-4" />
                                    {t('Assign Tenant')}
                                    {r.capacity > 1 ? '(s)' : ''}
                                </DropdownMenuItem>
                            )}
                            {!r.deleted_at && hasActiveLease && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        openMoveUnit(r);
                                    }}
                                >
                                    <Move className="size-4" />
                                    {t('Move Unit')}
                                </DropdownMenuItem>
                            )}
                            {!r.deleted_at &&
                                (r.capacity > occupants.length ||
                                    hasActiveLease) && (
                                    <DropdownMenuSeparator />
                                )}
                            {r.deleted_at ? (
                                <DropdownMenuItem onClick={() => restore(r)}>
                                    <RotateCcw className="size-4" />
                                    {t('Restore')}
                                </DropdownMenuItem>
                            ) : (
                                <>
                                    <DropdownMenuItem
                                        onClick={() => openEdit(r)}
                                    >
                                        <Pencil className="size-4" />
                                        {t('Edit')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => confirmDelete(r)}
                                    >
                                        <Trash2 className="size-4" />
                                        {t('Delete')}
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    const content = (
        <>
            <Head title={`${t('Units')} - ${property.name}`} />

            <div className="flex flex-col gap-4">
                <div className="flex items-center justify-end gap-2">
                    <Button onClick={openCreate}>{t('New Unit')}</Button>
                    <EntityTransferMenu
                        datasetLabel={t('Units')}
                        canImport={
                            auth.role === 'owner' ||
                            auth.permissions.includes('units.import')
                        }
                        canExport={
                            auth.role === 'owner' ||
                            auth.permissions.includes('units.export')
                        }
                        importHref={properties.units.transfer.import.url(
                            property,
                        )}
                        exportHref={properties.units.transfer.export.url(
                            property,
                            {
                                query: {
                                    search: currentSearch || undefined,
                                    status: currentStatus || undefined,
                                },
                            },
                        )}
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
                            placeholder={t('Search by name or floor...')}
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={(unit) =>
                        router.get(
                            properties.units.show.url({
                                property: property.slug,
                                unit: unit.slug,
                            }),
                        )
                    }
                    isRowInteractive={(unit) => !unit.deleted_at}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun={t('units')}
                    empty={{
                        message: t('No units yet.'),
                        createLabel: t('Create your first unit'),
                        onCreate: openCreate,
                    }}
                />
            </div>

            <UnitFormSheet
                key={`${currentEditingUnit?.id ?? 'new'}-${currentEditingUnit?.updated_at ?? ''}`}
                unit={currentEditingUnit}
                property={property}
                unitTypes={unitTypes}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignUnit && (
                <AssignTenantSheet
                    key={assignUnit.id}
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

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete unit')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to delete')}{' '}
                            <span className="font-medium">
                                {unitToDelete?.name}
                            </span>
                            ? {t('This action cannot be undone.')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialogOpen(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={destroy}>
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );

    return unitTypeWorkspace ? (
        <UnitTypeLayout property={property} unitType={unitTypeWorkspace} activeTab="units">
            {content}
        </UnitTypeLayout>
    ) : (
        <PropertyLayout property={property} activeTab="units">{content}</PropertyLayout>
    );
}
