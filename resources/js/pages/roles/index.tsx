import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    EllipsisVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { Heading } from '@/components/shared';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTable } from '@/hooks/use-table';
import roles, { destroy, clone } from '@/routes/roles';
import type { PaginatedData, RoleData, TableMeta } from '@/types';

type PageProps = {
    roles: PaginatedData<RoleData>;
    search?: string;
    status?: string;
    sort?: string;
    per_page?: number;
    table: TableMeta;
};

export default function Index({
    roles: data,
    search: currentSearch = '',
    status: currentStatus = '',
    sort: currentSort = 'label',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [deleteRole, setDeleteRole] = useState<RoleData | null>(null);
    const [cloneName, setCloneName] = useState('');
    const [cloneOpen, setCloneOpen] = useState(false);
    const [cloneTarget, setCloneTarget] = useState<RoleData | null>(null);

    const table = useTable({
        routeFn: () => roles.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
        },
        defaults: {
            sort: 'label',
            per_page: '15',
        },
    });

    function confirmDelete(role: RoleData) {
        setDeleteRole(role);
    }

    function executeDelete() {
        if (deleteRole) {
            router.delete(destroy.url(deleteRole));
            setDeleteRole(null);
        }
    }

    function openClone(role: RoleData) {
        setCloneTarget(role);
        setCloneName(`${role.name}-copy`);
        setCloneOpen(true);
    }

    function executeClone() {
        if (cloneTarget && cloneName) {
            router.post(clone.url(cloneTarget), {
                name: cloneName,
                label: cloneName,
            });
            setCloneOpen(false);
            setCloneTarget(null);
            setCloneName('');
        }
    }

    const columns: TableColumn<RoleData>[] = [
        {
            key: '_role',
            label: 'Role',
            sortable: true,
            render: (role) => (
                <div className="flex items-center gap-2">
                    {role.color && (
                        <span
                            className="inline-block size-3 shrink-0 rounded-full"
                            style={{ backgroundColor: role.color }}
                        />
                    )}
                    <span className="font-medium">{role.label}</span>
                </div>
            ),
        },
        {
            key: '_description',
            label: 'Description',
            render: (role) =>
                role.description ?? (
                    <span className="italic text-muted-foreground">No description</span>
                ),
        },
        {
            key: '_type',
            label: 'Type',
            render: (role) =>
                role.is_system ? (
                    <Badge variant="secondary">System</Badge>
                ) : (
                    <Badge variant="outline">Custom</Badge>
                ),
        },
        {
            key: 'users_count',
            label: 'Users',
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'permissions_count',
            label: 'Permissions',
            className: 'text-muted-foreground',
        },
        {
            key: '_status',
            label: 'Status',
            render: (role) =>
                role.is_active ? (
                    <Badge className="bg-green-600">Active</Badge>
                ) : (
                    <Badge variant="secondary">Disabled</Badge>
                ),
        },
        {
            key: '_actions',
            label: '',
            render: (role) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <span className="sr-only">Actions</span>
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <DropdownMenuItem asChild>
                            <Link href={roles.edit(role)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        {!role.is_system && (
                            <>
                                <DropdownMenuItem
                                    onClick={() => openClone(role)}
                                >
                                    <Copy className="size-4" />
                                    Clone
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => confirmDelete(role)}
                                >
                                    <Trash2 className="size-4" />
                                    Delete
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Roles & Permissions"
                        description="Create and manage custom roles"
                    />
                    <Link href={roles.create()}>
                        <Button>
                            <Plus className="size-4" />
                            Create Role
                        </Button>
                    </Link>
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
                            placeholder="Search roles..."
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={(role) => router.visit(roles.edit(role))}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun="roles"
                    empty={{
                        message: 'No roles yet.',
                        createLabel: 'Create a role',
                        onCreate: () => router.visit(roles.create()),
                    }}
                />
            </div>

            <Dialog
                open={deleteRole !== null}
                onOpenChange={(open) => !open && setDeleteRole(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete role?</DialogTitle>
                        <DialogDescription>
                            Users assigned to this role will keep their accounts
                            but lose this role. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteRole(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={executeDelete}>
                            <Trash2 className="size-4" />
                            Delete role
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cloneOpen} onOpenChange={setCloneOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Clone role</DialogTitle>
                        <DialogDescription>
                            Create a copy of &ldquo;{cloneTarget?.label}&rdquo;
                            with its permissions.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="clone-name">Role identifier</Label>
                        <Input
                            id="clone-name"
                            value={cloneName}
                            onChange={(e) => setCloneName(e.target.value)}
                            placeholder="e.g. finance-staff-copy"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCloneOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={executeClone} disabled={!cloneName}>
                            <Copy className="size-4" />
                            Clone
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Roles & Permissions', href: roles.index() }],
};
