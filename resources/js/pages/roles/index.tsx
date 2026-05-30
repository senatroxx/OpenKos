import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    Copy,
    EllipsisVertical,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
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
import roles, { destroy, clone } from '@/routes/roles';

type RoleData = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    color: string | null;
    guard_name: string;
    is_system: boolean;
    is_active: boolean;
    users_count: number;
    permissions_count: number;
    permissions: string[];
    created_at: string | null;
};

type PaginationLinks = { url: string | null; label: string; active: boolean };

type PageProps = {
    roles: {
        data: RoleData[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
};



export default function Index({
    roles: data,
    search: currentSearch = '',
    status: currentStatus = '',
    sort: currentSort = 'name',
    direction: currentDirection = 'asc',
    per_page: currentPerPage = 15,
}: PageProps) {
    const [deleteRole, setDeleteRole] = useState<RoleData | null>(null);
    const [cloneName, setCloneName] = useState('');
    const [cloneOpen, setCloneOpen] = useState(false);
    const [cloneTarget, setCloneTarget] = useState<RoleData | null>(null);
    const [searchValue, setSearchValue] = useState(currentSearch);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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

        router.get(roles.index(), params, { preserveState: true, replace: true });
    }

    function handleSearchChange(value: string) {
        setSearchValue(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            applyFilters({ search: value, page: '' });
        }, 300);
    }

    function toggleSort(column: string) {
        const direction =
            currentSort === column && currentDirection === 'asc' ? 'desc' : 'asc';

        applyFilters({ sort: column, direction, page: '' });
    }

    function goToPage(page: number) {
        applyFilters({ page: String(page) });
    }

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
            router.post(clone.url(cloneTarget), { name: cloneName, label: cloneName });
            setCloneOpen(false);
            setCloneTarget(null);
            setCloneName('');
        }
    }

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading title="Roles & Permissions" description="Create and manage custom roles" />
                    <Link href={roles.create()}>
                        <Button>
                            <Plus className="size-4" />
                            Create Role
                        </Button>
                    </Link>
                </div>

                <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search roles..."
                            className="pl-9"
                            value={searchValue}
                            onChange={(e) => handleSearchChange(e.target.value)}
                        />
                        {searchValue && (
                            <button
                                onClick={() => {
                                    setSearchValue('');
                                    applyFilters({ search: '', page: '' });
                                }}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>

                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        {[
                            { value: '', label: 'All' },
                            { value: 'active', label: 'Active' },
                            { value: 'disabled', label: 'Disabled' },
                        ].map((status) => (
                            <Button
                                key={status.value}
                                variant={currentStatus === status.value ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => applyFilters({ status: status.value, page: '' })}
                            >
                                {status.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {data.data.length === 0 ? (
                    <EmptyState message="No roles yet." createLabel="Create a role" onCreate={() => router.visit(roles.create())} />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th
                                        className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
                                        onClick={() => toggleSort('label')}
                                    >
                                        Role
                                        {currentSort === 'label' ? (
                                            currentDirection === 'asc' ? <ChevronUp className="ml-1 inline size-3.5" /> : <ChevronDown className="ml-1 inline size-3.5" />
                                        ) : (
                                            <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />
                                        )}
                                    </th>
                                    <th className="px-4 py-3 font-medium">Description</th>
                                    <th className="px-4 py-3 font-medium">Type</th>
                                    <th
                                        className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
                                        onClick={() => toggleSort('users_count')}
                                    >
                                        Users
                                        {currentSort === 'users_count' ? (
                                            currentDirection === 'asc' ? <ChevronUp className="ml-1 inline size-3.5" /> : <ChevronDown className="ml-1 inline size-3.5" />
                                        ) : (
                                            <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />
                                        )}
                                    </th>
                                    <th className="px-4 py-3 font-medium">Permissions</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((role) => (
                                    <tr
                                        key={role.id}
                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                        onClick={() => router.visit(roles.edit(role))}
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                {role.color && (
                                                    <span
                                                        className="inline-block size-3 rounded-full shrink-0"
                                                        style={{ backgroundColor: role.color }}
                                                    />
                                                )}
                                                <span className="font-medium">{role.label}</span>
                                            </div>
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3 text-muted-foreground">
                                            {role.description ?? <span className="italic">No description</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            {role.is_system ? (
                                                <Badge variant="secondary">System</Badge>
                                            ) : (
                                                <Badge variant="outline">Custom</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{role.users_count}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{role.permissions_count}</td>
                                        <td className="px-4 py-3">
                                            {role.is_active ? (
                                                <Badge className="bg-green-600">Active</Badge>
                                            ) : (
                                                <Badge variant="secondary">Disabled</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8">
                                                        <span className="sr-only">Actions</span>
                                                        <EllipsisVertical className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={roles.edit(role)}>
                                                            <Pencil className="size-4" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {!role.is_system && (
                                                        <>
                                                            <DropdownMenuItem onClick={() => openClone(role)}>
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
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                            <div className="flex items-center gap-4">
                                <p className="text-muted-foreground">
                                    Showing {data.from} to {data.to} of {data.total} roles
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">Per page</span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        value={currentPerPage}
                                        onChange={(event) =>
                                            applyFilters({ per_page: event.target.value, page: '' })
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
                                    onClick={() => goToPage(data.current_page - 1)}
                                >
                                    Previous
                                </Button>

                                {data.links
                                    .filter((link) => !isNaN(Number(link.label)))
                                    .map((link) => (
                                        <Button
                                            key={link.label}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => goToPage(Number(link.label))}
                                        >
                                            {link.label}
                                        </Button>
                                    ))}

                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={data.current_page === data.last_page}
                                    onClick={() => goToPage(data.current_page + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={deleteRole !== null} onOpenChange={(open) => !open && setDeleteRole(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete role?</DialogTitle>
                        <DialogDescription>
                            Users assigned to this role will keep their accounts but lose this role. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteRole(null)}>
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
                            Create a copy of &ldquo;{cloneTarget?.label}&rdquo; with its permissions.
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
                        <Button variant="outline" onClick={() => setCloneOpen(false)}>
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
