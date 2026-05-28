import { Form, Head, router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    EllipsisVertical,
    Eye,
    KeyRound,
    Pencil,
    Search,
    ShieldOff,
    UserPlus,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import users, {
    destroy,
    resendInvitation,
    resetPassword,
    store,
    update,
} from '@/routes/users';

type Property = { id: number; name: string };
type RoleOption = { value: string; label: string };
type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    properties: Property[];
    is_active: boolean;
    status: 'active' | 'invited' | 'disabled';
    invited_at: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
};
type PaginationLinks = { url: string | null; label: string; active: boolean };
type PageProps = {
    users: {
        data: ManagedUser[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
        links: PaginationLinks[];
    };
    properties: Property[];
    roles: RoleOption[];
    search?: string;
    role?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
};

const SORTABLE = ['name', 'email', 'last_login_at'] as const;

function StatusBadge({ user }: { user: ManagedUser }) {
    if (user.status === 'invited') {
        return <Badge variant="outline">Invited</Badge>;
    }

    if (user.status === 'active') {
        return <Badge className="bg-green-600">Active</Badge>;
    }

    return <Badge variant="secondary">Disabled</Badge>;
}

function roleLabel(value: string | null, roles: RoleOption[]) {
    return roles.find((role) => role.value === value)?.label ?? 'Owner';
}

function formatDate(value: string | null) {
    if (!value) {
        return 'Never';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function Index({
    users: data,
    properties,
    roles,
    search: currentSearch = '',
    role: currentRole = '',
    status: currentStatus = '',
    sort: currentSort = 'name',
    direction: currentDirection = 'asc',
    per_page: currentPerPage = 15,
}: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [viewingUser, setViewingUser] = useState<ManagedUser | null>(null);
    const [searchValue, setSearchValue] = useState(currentSearch);
    const [roleFilterOpen, setRoleFilterOpen] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const roleFilterOptions = [
        { value: '', label: 'All roles' },
        { value: 'owner', label: 'Owner' },
        ...roles,
    ];

    const [formKey, setFormKey] = useState(0);

    function openInvite() {
        setEditingUser(null);
        setFormKey((k) => k + 1);
        setFormOpen(true);
    }

    function openEdit(user: ManagedUser) {
        setEditingUser(user);
        setFormKey((k) => k + 1);
        setFormOpen(true);
    }

    function openDetail(user: ManagedUser) {
        setViewingUser(user);
        setDetailOpen(true);
    }

    function applyFilters(overrides: Record<string, string>) {
        const params: Record<string, string> = {
            search: searchValue,
            role: currentRole,
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

        router.get(users.index(), params, { preserveState: true, replace: true });
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

    function disableAccess(user: ManagedUser) {
        if (confirm(`Disable access for ${user.name}?`)) {
            router.delete(destroy.url(user));
        }
    }

    function sendReset(user: ManagedUser) {
        if (confirm(`Send password reset to ${user.email}?`)) {
            router.post(resetPassword.url(user));
        }
    }

    function resendInvite(user: ManagedUser) {
        if (confirm(`Resend invitation to ${user.email}?`)) {
            router.post(resendInvitation.url(user));
        }
    }

    function roleFilterLabel() {
        return roleFilterOptions.find((role) => role.value === currentRole)?.label ?? 'All roles';
    }

    function SortIcon({ column }: { column: string }) {
        if (currentSort !== column) {
            return <ChevronsUpDown className="ml-1 inline size-3.5 opacity-40" />;
        }

        return currentDirection === 'asc' ? (
            <ChevronUp className="ml-1 inline size-3.5" />
        ) : (
            <ChevronDown className="ml-1 inline size-3.5" />
        );
    }

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading title="Users" description="Invite staff and manage access" />
                    <Button onClick={openInvite}>
                        <UserPlus className="size-4" />
                        Invite User
                    </Button>
                </div>

                <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or email..."
                            className="pl-9"
                            value={searchValue}
                            onChange={(event) => handleSearchChange(event.target.value)}
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

                    <Popover open={roleFilterOpen} onOpenChange={setRoleFilterOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="outline"
                                role="combobox"
                                aria-expanded={roleFilterOpen}
                                className="w-44 justify-between font-normal"
                            >
                                {roleFilterLabel()}
                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-44 p-0">
                            <Command>
                                <CommandInput placeholder="Search role..." />
                                <CommandList>
                                    <CommandEmpty>No role found.</CommandEmpty>
                                    <CommandGroup>
                                        {roleFilterOptions.map((role) => (
                                            <CommandItem
                                                key={role.value || 'all'}
                                                value={role.label}
                                                onSelect={() => {
                                                    applyFilters({ role: role.value, page: '' });
                                                    setRoleFilterOpen(false);
                                                }}
                                            >
                                                <Check
                                                    className={`mr-2 size-4 ${
                                                        currentRole === role.value
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />
                                                {role.label}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>

                    <div className="flex items-center gap-1 rounded-lg border p-1">
                        {[
                            { value: '', label: 'All' },
                            { value: 'active', label: 'Active' },
                            { value: 'invited', label: 'Invited' },
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
                    <EmptyState message="No users yet." createLabel="Invite a user" onCreate={openInvite} />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    {SORTABLE.map((column) => (
                                        <th
                                            key={column}
                                            className="cursor-pointer px-4 py-3 font-medium select-none hover:text-foreground"
                                            onClick={() => toggleSort(column)}
                                        >
                                            {column === 'last_login_at'
                                                ? 'Last Login'
                                                : column === 'name'
                                                  ? 'Name'
                                                  : 'Email'}
                                            <SortIcon column={column} />
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 font-medium">Role</th>
                                    <th className="px-4 py-3 font-medium">Assigned Properties</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="w-12 px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                                        onClick={() => openDetail(user)}
                                    >
                                        <td className="px-4 py-3 font-medium">{user.name}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{formatDate(user.last_login_at)}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {roleLabel(user.role, roles)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {user.properties.length > 0
                                                ? user.properties.map((property) => property.name).join(', ')
                                                : user.role === 'owner'
                                                  ? 'All properties'
                                                  : 'No properties'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge user={user} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild onClick={(event) => event.stopPropagation()}>
                                                    <Button variant="ghost" size="icon" className="size-8">
                                                        <EllipsisVertical className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" onClick={(event) => event.stopPropagation()}>
                                                    <DropdownMenuItem onClick={() => openDetail(user)}>
                                                        <Eye className="size-4" />
                                                        View
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => openEdit(user)}>
                                                        <Pencil className="size-4" />
                                                        Edit / Assign Property
                                                    </DropdownMenuItem>
                                                    {user.status === 'active' && (
                                                        <DropdownMenuItem onClick={() => sendReset(user)}>
                                                            <KeyRound className="size-4" />
                                                            Reset Password
                                                        </DropdownMenuItem>
                                                    )}
                                                    {user.status === 'invited' && (
                                                        <DropdownMenuItem onClick={() => resendInvite(user)}>
                                                            <UserPlus className="size-4" />
                                                            Resend Invite Link
                                                        </DropdownMenuItem>
                                                    )}
                                                    {user.status !== 'disabled' && (
                                                        <DropdownMenuItem variant="destructive" onClick={() => disableAccess(user)}>
                                                            <ShieldOff className="size-4" />
                                                            Disable Access
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
                                    Showing {data.from} to {data.to} of {data.total} users
                                </p>

                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">Per page</span>
                                    <select
                                        className="rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        value={currentPerPage}
                                        onChange={(event) =>
                                            applyFilters({
                                                per_page: event.target.value,
                                                page: '',
                                            })
                                        }
                                    >
                                        {[10, 15, 25, 50].map((perPage) => (
                                            <option key={perPage} value={perPage}>
                                                {perPage}
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

            <UserFormSheet
                key={formKey}
                user={editingUser}
                open={formOpen}
                onOpenChange={setFormOpen}
                roles={roles}
                properties={properties}
            />

            <UserDetailSheet
                user={viewingUser}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={(user) => {
                    setDetailOpen(false);
                    openEdit(user);
                }}
                onDisable={disableAccess}
                onResetPassword={sendReset}
                onResendInvitation={resendInvite}
                roles={roles}
            />
        </>
    );
}

function UserFormSheet({
    user,
    open,
    onOpenChange,
    roles,
    properties,
}: {
    user: ManagedUser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: RoleOption[];
    properties: Property[];
}) {
    const isEdit = Boolean(user);
    const [selectedPropertyIds, setSelectedPropertyIds] = useState<number[]>(
        () => user?.properties.map((property) => property.id) ?? [],
    );

    const previousUserIdRef = useRef(user?.id);

    if (previousUserIdRef.current !== user?.id) {
        previousUserIdRef.current = user?.id;
        setSelectedPropertyIds(
            user?.properties.map((property) => property.id) ?? [],
        );
    }

    const canEditRole = user?.role !== 'owner';
    const formProps = isEdit
        ? { action: update.url(user!), method: 'put' as const }
        : { action: store.url(), method: 'post' as const };

    function toggleProperty(propertyId: number, checked: boolean) {
        setSelectedPropertyIds((current) =>
            checked ? [...current, propertyId] : current.filter((id) => id !== propertyId),
        );
    }

    return (
        <Sheet key={user?.id ?? 'new'} open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{isEdit ? 'Edit User' : 'Invite User'}</SheetTitle>
                    <SheetDescription>
                        {isEdit ? 'Update access and property assignments' : 'Invite an admin or staff member'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form {...formProps} onSuccess={() => onOpenChange(false)}>
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" defaultValue={user?.name ?? ''} required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input id="email" name="email" type="email" defaultValue={user?.email ?? ''} required />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        name="role"
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                                        defaultValue={canEditRole ? (user?.role ?? 'admin') : 'admin'}
                                        disabled={!canEditRole}
                                    >
                                        {roles.map((role) => (
                                            <option key={role.value} value={role.value}>
                                                {role.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.role} />
                                </div>

                                <input type="hidden" name="is_active" value={user?.is_active === false ? '0' : '1'} />
                                {selectedPropertyIds.map((propertyId) => (
                                    <input key={propertyId} type="hidden" name="property_ids[]" value={propertyId} />
                                ))}

                                <div className="grid gap-3">
                                    <Label>Assigned Properties</Label>
                                    <div className="max-h-56 space-y-2 overflow-y-auto rounded-lg border p-3">
                                        {properties.map((property) => (
                                            <label key={property.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={selectedPropertyIds.includes(property.id)}
                                                    onCheckedChange={(checked) => toggleProperty(property.id, checked === true)}
                                                />
                                                {property.name}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError message={errors.property_ids} />
                                </div>

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button variant="outline" type="button" onClick={() => onOpenChange(false)} disabled={processing}>
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>{isEdit ? 'Save' : 'Send Invite'}</Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function UserDetailSheet({
    user,
    open,
    onOpenChange,
    onEdit,
    onDisable,
    onResetPassword,
    onResendInvitation,
    roles,
}: {
    user: ManagedUser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: (user: ManagedUser) => void;
    onDisable: (user: ManagedUser) => void;
    onResetPassword: (user: ManagedUser) => void;
    onResendInvitation: (user: ManagedUser) => void;
    roles: RoleOption[];
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{user?.name ?? 'User'}</SheetTitle>
                    <SheetDescription>{user?.email}</SheetDescription>
                </SheetHeader>

                {user && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pb-6 pt-4">
                        <div className="space-y-6">
                            <section>
                                <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    Status
                                </h3>
                                <StatusBadge user={user} />
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    Account
                                </h3>
                                <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Name</span>
                                        <span className="text-sm font-medium">{user.name}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Email</span>
                                        <span className="text-sm">{user.email}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Role</span>
                                        <Badge variant="outline">{roleLabel(user.role, roles)}</Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Email verified</span>
                                        <span className="text-sm">{user.email_verified_at ? 'Yes' : 'No'}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Last login</span>
                                        <span className="text-sm tabular-nums">{formatDate(user.last_login_at)}</span>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    Properties
                                </h3>
                                <div className="rounded-lg border p-4">
                                    <p className="text-sm text-muted-foreground">
                                        {user.properties.length > 0
                                            ? user.properties.map((property) => property.name).join(', ')
                                            : user.role === 'owner'
                                              ? 'All properties'
                                              : 'No properties assigned'}
                                    </p>
                                </div>
                            </section>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {user.status === 'invited' && (
                                <Button variant="outline" onClick={() => onResendInvitation(user)}>
                                    <UserPlus className="size-4" />
                                    Resend Invite Link
                                </Button>
                            )}

                            <Button variant="outline" onClick={() => onEdit(user)}>
                                <Pencil className="size-4" />
                                Edit / Assign Property
                            </Button>

                            {user.status === 'active' && (
                                <Button variant="outline" onClick={() => onResetPassword(user)}>
                                    <KeyRound className="size-4" />
                                    Reset Password
                                </Button>
                            )}

                            {user.status !== 'disabled' && (
                                <Button variant="destructive" onClick={() => onDisable(user)}>
                                    <ShieldOff className="size-4" />
                                    Disable Access
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Users', href: users.index() }],
};
