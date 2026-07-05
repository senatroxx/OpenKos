import { Head, router } from '@inertiajs/react';
import { EllipsisVertical, ExternalLink, Eye, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { PropertyDetailSheet, PropertyFormSheet } from '@/components/features';
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
import properties from '@/routes/properties';
import type { PaginatedData, TableMeta } from '@/types';

type ManagedProperty = {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label?: string;
    address: string | null;
    region_id: number | null;
    city_id: number | null;
    postal_code: string | null;
    phone: string | null;
    is_active: boolean;
    rooms_count: number;
    occupied_rooms_count: number;
    tenants_count: number;
    city?: { id: number; name: string } | null;
};

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
        setDialogOpen(true);
    }

    function archive(property: ManagedProperty) {
        if (confirm('Are you sure you want to archive this property?')) {
            router.delete(properties.destroy.url(property));
        }
    }

    const columns: TableColumn<ManagedProperty>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            render: (p) => (
                <Badge variant="outline">
                    {p.type_label ?? p.type}
                </Badge>
            ),
        },
        {
            key: 'city',
            label: 'City',
            sortable: true,
            className: 'text-muted-foreground',
            render: (p) => p.city?.name ?? '\u2014',
        },
        {
            key: 'rooms_count',
            label: 'Total Rooms',
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: 'occupied_rooms_count',
            label: 'Occupied',
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: 'tenants_count',
            label: 'Tenants',
            sortable: true,
            className: 'tabular-nums',
        },
        {
            key: '_status',
            label: 'Status',
            render: (p) =>
                p.is_active ? (
                    <Badge variant="default" className="bg-green-600">
                        Active
                    </Badge>
                ) : (
                    <Badge variant="secondary">Archived</Badge>
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
                        <DropdownMenuItem onClick={() => router.get(properties.show.url(p))}>
                            <ExternalLink className="size-4" />
                            Open Workspace
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(p)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(p)}>
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => archive(p)}
                        >
                            <Trash2 className="size-4" />
                            Archive
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Properties" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Properties"
                        description="Manage your properties"
                    />

                    <Button onClick={openCreate}>New Property</Button>
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
                            placeholder="Search by name, province, or city..."
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
                    noun="properties"
                    empty={{
                        message: 'No properties yet.',
                        createLabel: 'Create your first property',
                        onCreate: openCreate,
                    }}
                />
            </div>

            <PropertyDetailSheet
                property={viewingProperty}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
            />

            <PropertyFormSheet
                property={editingProperty}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
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
    ],
};
