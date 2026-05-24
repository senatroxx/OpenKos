import { Head, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    EllipsisVertical,
    Eye,
    Pencil,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import PropertyDetailSheet from '@/components/property-detail-sheet';
import PropertyFormSheet from '@/components/property-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import properties from '@/routes/properties';

type Property = {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    phone: string | null;
    is_active: boolean;
    rooms_count: number;
    occupied_rooms_count: number;
    tenants_count: number;
};

type PaginationLinks = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    properties: {
        data: Property[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    sort?: string;
    direction?: string;
    search?: string;
    status?: string;
    per_page?: number;
};

const SORTABLE = [
    'name',
    'city',
    'rooms_count',
    'occupied_rooms_count',
    'tenants_count',
] as const;

export default function Index({
    properties: data,
    sort: currentSort = 'name',
    direction: currentDirection = 'asc',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingProperty, setEditingProperty] = useState<Property | null>(
        null,
    );

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingProperty, setViewingProperty] = useState<Property | null>(
        null,
    );

    const [searchValue, setSearchValue] = useState(currentSearch);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    function openCreate() {
        setEditingProperty(null);
        setDialogOpen(true);
    }

    function openEdit(property: Property) {
        setEditingProperty(property);
        setDialogOpen(true);
    }

    function openDetail(property: Property) {
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

    function archive(property: Property) {
        if (confirm('Are you sure you want to archive this property?')) {
            router.delete(properties.destroy.url(property));
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

        router.get(properties.index(), params, {
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

            router.get(properties.index(), params, {
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
            <Head title="Properties" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Properties"
                        description="Manage your properties"
                    />

                    <Button onClick={openCreate}>New Property</Button>
                </div>

                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or city..."
                            className="pl-9"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                        {searchValue && (
                            <button
                                onClick={() => {
                                    if (debounceRef.current) {
                                        clearTimeout(
                                            debounceRef.current,
                                        );
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
                        {(['', 'active', 'archived'] as const).map((value) => (
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
                                      : 'Archived'}
                            </Button>
                        ))}
                    </div>
                </div>

                {data.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12">
                            <p className="text-sm text-muted-foreground">
                                No properties yet.
                            </p>

                            <Button onClick={openCreate}>
                                Create your first property
                            </Button>
                        </CardContent>
                    </Card>
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
                                                : col === 'city'
                                                  ? 'City'
                                                  : col === 'rooms_count'
                                                    ? 'Total Rooms'
                                                    : col ===
                                                        'occupied_rooms_count'
                                                      ? 'Occupied'
                                                      : 'Tenants'}
                                            <SortIcon column={col} />
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((property) => (
                                    <tr
                                        key={property.id}
                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                        onClick={() => openDetail(property)}
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {property.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {property.city ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {property.rooms_count}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {property.occupied_rooms_count}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {property.tenants_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            {property.is_active ? (
                                                <Badge
                                                    variant="default"
                                                    className="bg-green-600"
                                                >
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Archived
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
                                                            openDetail(property)
                                                        }
                                                    >
                                                        <Eye className="size-4" />
                                                        View
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            openEdit(property)
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            archive(property)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                        Archive
                                                    </DropdownMenuItem>
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
                                    {data.total} properties
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-xs">
                                        Per page
                                    </span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        value={currentPerPage}
                            onChange={(e) =>
                                handleSearchChange(e.target.value)
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
