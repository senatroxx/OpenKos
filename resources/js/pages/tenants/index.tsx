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
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
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

type Room = {
    id: number;
    name: string;
    floor: string | null;
};

type Property = {
    id: number;
    name: string;
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    room: Room | null;
    property: Property | null;
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
    sort?: string;
    direction?: string;
    search?: string;
    status?: string;
    per_page?: number;
};

const SORTABLE = ['name', 'phone', 'email'] as const;

export default function Index({
    tenants: data,
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
                            placeholder="Search by name, phone, email, or ID card..."
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
                                            {col === 'name'
                                                ? 'Name'
                                                : col === 'phone'
                                                  ? 'Phone'
                                                  : 'Email'}
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
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {tenant.email ?? '—'}
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
                                                    className="text-amber-600 border-amber-300"
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
            />

            <TenantFormSheet
                tenant={editingTenant}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
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
