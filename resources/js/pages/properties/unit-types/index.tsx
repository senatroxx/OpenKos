import { Head, router, usePage } from '@inertiajs/react';
import {
    EllipsisVertical,
    Eye,
    EyeOff,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import UnitTypeDetailSheet from '@/components/features/properties/unit-type-detail-sheet';
import UnitTypeFormSheet from '@/components/features/properties/unit-type-form-sheet';
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
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type {
    Auth,
    ListingUnitType,
    UnitTypesPageProps,
    UnitType,
} from '@/types';
import { PropertyLayout } from '../layout';

function unitCountLabel(
    count: number,
    singular: string,
    plural = `${singular}s`,
): string {
    return `${count} ${t(count === 1 ? singular : plural)}`;
}

function readinessLabel(option: ListingUnitType): string {
    return option.status === 'ready' || option.status === 'excluded'
        ? 'Ready'
        : 'Needs attention';
}

export default function Index({
    property,
    unitTypes: data,
    amenities,
    rentalOptions,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: UnitTypesPageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManage = auth.permissions.includes('properties.update');
    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingUnitTypeId, setViewingUnitTypeId] = useState<number | null>(
        null,
    );
    const [editingUnitType, setEditingUnitType] = useState<UnitType | null>(
        null,
    );
    const [formOpen, setFormOpen] = useState(false);
    const [publishingId, setPublishingId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [unitTypeToDelete, setUnitTypeToDelete] = useState<UnitType | null>(
        null,
    );

    const table = useTable({
        routeFn: () => ({ url: properties.unitTypes.index.url(property) }),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
        },
        defaults: { sort: 'name', per_page: '15' },
    });

    const viewingUnitType = viewingUnitTypeId
        ? (data.data.find((unitType) => unitType.id === viewingUnitTypeId) ??
          null)
        : null;
    const viewingOption = viewingUnitType
        ? (rentalOptions.find((option) => option.id === viewingUnitType.id) ??
          null)
        : null;
    const currentEditingUnitType = editingUnitType
        ? (data.data.find((unitType) => unitType.id === editingUnitType.id) ??
          editingUnitType)
        : null;

    function openCreate(): void {
        setEditingUnitType(null);
        setFormOpen(true);
    }

    function openDetail(unitType: UnitType): void {
        if (unitType.deleted_at) {
            return;
        }

        setViewingUnitTypeId(unitType.id);
        setDetailOpen(true);
    }

    function editFromDetail(): void {
        if (!viewingUnitType) {
            return;
        }

        setEditingUnitType(viewingUnitType);
        setDetailOpen(false);
        setFormOpen(true);
    }

    function togglePublication(): void {
        if (!viewingOption) {
            return;
        }

        publishUnitType(viewingUnitType ?? undefined);
    }

    function publishUnitType(unitType?: UnitType): void {
        if (!unitType) {
            return;
        }

        const option = getOption(unitType);

        if (!option || (!option.is_included && !option.is_viable_if_included)) {
            return;
        }

        setPublishingId(option.id);
        router.patch(
            properties.unitTypes.publication.update.url({
                property: property.slug,
                unitType: option.id,
            }),
            { is_published: !option.is_included },
            { preserveScroll: true, onFinish: () => setPublishingId(null) },
        );
    }

    function confirmDelete(unitType = viewingUnitType): void {
        if (!unitType) {
            return;
        }

        setUnitTypeToDelete(unitType);
        setDeleteDialogOpen(true);
    }

    function destroy(): void {
        if (!unitTypeToDelete) {
            return;
        }

        router.delete(
            properties.unitTypes.destroy.url({
                property: property.slug,
                unitType: unitTypeToDelete.id,
            }),
            {
                onSuccess: () => {
                    setDeleteDialogOpen(false);
                    setDetailOpen(false);
                    setViewingUnitTypeId(null);
                    setUnitTypeToDelete(null);
                },
            },
        );
    }

    function restoreUnitType(unitType: UnitType): void {
        router.post(
            properties.unitTypes.restore.url({
                property: property.slug,
                unitType: unitType.id,
            }),
            {},
            { preserveScroll: true },
        );
    }

    function getOption(unitType: UnitType): ListingUnitType | null {
        return (
            rentalOptions.find((option) => option.id === unitType.id) ?? null
        );
    }

    const columns: TableColumn<UnitType>[] = [
        {
            key: 'name',
            label: 'Unit Type',
            className: 'min-w-48 align-top font-medium',
            render: (unitType) => (
                <div className="flex items-center gap-2">
                    <span>{unitType.name}</span>
                    {unitType.deleted_at && (
                        <Badge variant="outline">{t('Deleted')}</Badge>
                    )}
                    {!unitType.deleted_at && !unitType.is_active && (
                        <Badge variant="outline">{t('Inactive')}</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'listing',
            label: 'Listing',
            className: 'align-top whitespace-nowrap',
            render: (unitType) => {
                const option = getOption(unitType);

                return (
                    <Badge
                        variant={
                            option?.is_included ? 'secondary' : 'outline'
                        }
                    >
                        {t(option?.is_included ? 'Listed' : 'Not listed')}
                    </Badge>
                );
            },
        },
        {
            key: 'inventory',
            label: 'Inventory',
            className: 'align-top whitespace-nowrap',
            render: (unitType) => {
                if (unitType.deleted_at) {
                    return '—';
                }

                const option = getOption(unitType);

                return `${unitCountLabel(unitType.units_count ?? 0, 'unit')} · ${unitCountLabel(option?.available_units ?? 0, 'available', 'available')}`;
            },
        },
        {
            key: 'starting_price',
            label: 'Starting price',
            className: 'align-top whitespace-nowrap',
            render: (unitType) => {
                if (unitType.deleted_at) {
                    return '—';
                }

                const price = getOption(unitType)?.starting_price;

                return price
                    ? `${formatPrice(price.amount, price.currency)} ${price.billing_label}`
                    : '—';
            },
        },
        {
            key: 'readiness',
            label: 'Readiness',
            className: 'min-w-44 max-w-xs align-top',
            render: (unitType) => {
                if (unitType.deleted_at) {
                    return <span className="text-muted-foreground">—</span>;
                }

                const option = getOption(unitType);

                return option ? (
                    <div>
                        <p className="font-medium">
                            {t(readinessLabel(option))}
                        </p>
                        {option.reason_label && (
                            <p className="mt-1 text-amber-700 dark:text-amber-300">
                                {t(option.reason_label)}
                            </p>
                        )}
                    </div>
                ) : null;
            },
        },
        {
            key: '_actions',
            label: '',
            className: 'w-12 text-right',
            render: (unitType) => {
                if (unitType.deleted_at) {
                    return canManage ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                asChild
                                onClick={(event) => event.stopPropagation()}
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label={t('Unit Type actions')}
                                >
                                    <EllipsisVertical
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                onClick={(event) => event.stopPropagation()}
                            >
                                <DropdownMenuItem
                                    onSelect={() => restoreUnitType(unitType)}
                                >
                                    <RotateCcw className="size-4" />
                                    {t('Restore')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null;
                }

                const option = getOption(unitType);
                const canPublish = Boolean(
                    option &&
                    (option.is_included || option.is_viable_if_included),
                );

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            asChild
                            onClick={(event) => event.stopPropagation()}
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={t('Unit Type actions')}
                            >
                                <EllipsisVertical
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <DropdownMenuItem
                                onSelect={() => openDetail(unitType)}
                            >
                                <Eye className="size-4" />
                                {t('View')}
                            </DropdownMenuItem>
                            {canManage && (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => router.visit(properties.unitTypes.rates.index.url({ property, unitType }))}
                                    >
                                        {t('Manage pricing')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            setEditingUnitType(unitType);
                                            setFormOpen(true);
                                        }}
                                    >
                                        <Pencil className="size-4" />
                                        {t('Edit')}
                                    </DropdownMenuItem>
                                    {canPublish && (
                                        <DropdownMenuItem
                                            disabled={
                                                publishingId === unitType.id
                                            }
                                            onSelect={() =>
                                                publishUnitType(unitType)
                                            }
                                        >
                                            {option?.is_included ? (
                                                <EyeOff className="size-4" />
                                            ) : (
                                                <Eye className="size-4" />
                                            )}
                                            {t(
                                                option?.is_included
                                                    ? 'Unpublish'
                                                    : 'Publish',
                                            )}
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => confirmDelete(unitType)}
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

    return (
        <PropertyLayout property={property} activeTab="unit-types">
            <Head title={`${t('Unit Types')} - ${property.name}`} />

            <div className="space-y-6">
                {canManage && (
                    <div className="flex items-center justify-end">
                        <Button onClick={openCreate}>
                            {t('New Unit Type')}
                        </Button>
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
                            placeholder={t('Search Unit Types...')}
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    rowKey={(unitType) => unitType.id}
                    onRowClick={openDetail}
                    isRowInteractive={(unitType) => !unitType.deleted_at}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun={t('Unit Types')}
                    empty={{
                        message: t(
                            'No Unit Types yet. Create a reusable type before assigning it to units.',
                        ),
                        ...(canManage
                            ? {
                                  createLabel: t('Create your first Unit Type'),
                                  onCreate: openCreate,
                              }
                            : {}),
                    }}
                />
            </div>

            <UnitTypeDetailSheet
                unitType={viewingUnitType}
                option={viewingOption}
                open={detailOpen}
                canManage={canManage}
                publishing={publishingId !== null}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onTogglePublication={togglePublication}
                onDelete={confirmDelete}
            />

            <UnitTypeFormSheet
                key={`${currentEditingUnitType?.id ?? 'new'}-${currentEditingUnitType?.updated_at ?? ''}`}
                property={property}
                unitType={currentEditingUnitType}
                amenities={amenities}
                open={formOpen}
                onOpenChange={setFormOpen}
            />

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete Unit Type')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to delete')}{' '}
                            <span className="font-medium">
                                {unitTypeToDelete?.name}
                            </span>
                            ?
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
        </PropertyLayout>
    );
}
