import { Head, router } from '@inertiajs/react';
import {
    EllipsisVertical,
    ExternalLink,
    Eye,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { PropertyDetailSheet, PropertyFormSheet } from '@/components/features';
import { Heading } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { ManagedProperty, PaginatedData, TableMeta } from '@/types';

type PageProps = {
    properties: PaginatedData<ManagedProperty>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
    regions: {
        id: number;
        name: string;
        cities: { id: number; name: string }[];
    }[];
};

export default function Index({
    properties: data,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingProperty, setEditingProperty] =
        useState<ManagedProperty | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingProperty, setViewingProperty] =
        useState<ManagedProperty | null>(null);
    const [archiveConfirm, setArchiveConfirm] =
        useState<ManagedProperty | null>(null);

    const table = useTable({
        routeFn: () => properties.index(),
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
        setEditingProperty(null);
        setDialogOpen(true);
    }

    function openEdit(property: ManagedProperty) {
        setEditingProperty(property);
        setDialogOpen(true);
    }

    function openDetail(property: ManagedProperty) {
        setViewingProperty(property);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingProperty) {
            return;
        }

        setEditingProperty(viewingProperty);
        setDetailOpen(false);
        setDialogOpen(true);
    }

    function archive(property: ManagedProperty) {
        setArchiveConfirm(property);
    }

    function confirmArchive() {
        if (!archiveConfirm) {
            return;
        }

        router.delete(properties.destroy.url(archiveConfirm));
        setArchiveConfirm(null);
    }

    function restore(property: ManagedProperty) {
        router.post(properties.restore.url(property));
    }

    const columns: TableColumn<ManagedProperty>[] = [
        {
            key: 'name',
            label: t('Name'),
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'type',
            label: t('Type'),
            sortable: true,
            render: (p) => (
                <Badge variant="outline">{p.type_label ?? p.type}</Badge>
            ),
        },
        {
            key: 'city',
            label: t('City'),
            sortable: true,
            className: 'text-muted-foreground',
            render: (p) => p.city?.name ?? '\u2014',
        },
        {
            key: 'units_count',
            label: t('Total Units'),
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: 'occupied_units_count',
            label: t('Occupied'),
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: 'tenants_count',
            label: t('Tenants'),
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: '_status',
            label: t('Status'),
            render: (p) => (
                <StatusBadge
                    domain="property"
                    value={p.is_active ? 'active' : 'archived'}
                />
            ),
        },
        {
            key: '_actions',
            label: '',
            render: (p) => (
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
                            onClick={() => router.get(properties.show.url(p))}
                        >
                            <ExternalLink className="size-4" />
                            {t('Open Workspace')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(p)}>
                            <Eye className="size-4" />
                            {t('View')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(p)}>
                            <Pencil className="size-4" />
                            {t('Edit')}
                        </DropdownMenuItem>
                        {p.is_active ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => archive(p)}
                            >
                                <Trash2 className="size-4" />
                                {t('Archive')}
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem onClick={() => restore(p)}>
                                <RotateCcw className="size-4" />
                                {t('Restore')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Properties')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('Properties')}
                        description={t('Manage your properties')}
                    />

                    <Button onClick={openCreate}>{t('New Property')}</Button>
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
                            placeholder={t(
                                'Search by name, province, or city...',
                            )}
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
                    noun={t('properties')}
                    empty={{
                        message: t('No properties yet.'),
                        createLabel: t('Create your first property'),
                        onCreate: openCreate,
                    }}
                />
            </div>

            <PropertyDetailSheet
                key={viewingProperty?.id ?? 'new'}
                property={viewingProperty}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
            />

            <PropertyFormSheet
                key={editingProperty?.id ?? 'new'}
                property={editingProperty}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            <Dialog
                open={archiveConfirm !== null}
                onOpenChange={() => setArchiveConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Archive property')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to archive')}{' '}
                            <span className="font-medium">
                                {archiveConfirm?.name}
                            </span>
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveConfirm(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmArchive}>
                            {t('Archive')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
    ],
};
