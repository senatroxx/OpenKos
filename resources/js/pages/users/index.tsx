import { Form, Head, router } from '@inertiajs/react';
import {
    EllipsisVertical,
    Eye,
    KeyRound,
    Pencil,
    ShieldOff,
    UserPlus,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { Heading, InputError } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTable } from '@/hooks/use-table';
import users, {
    destroy,
    resendInvitation,
    resetPassword,
    store,
    update,
} from '@/routes/users';
import type { PaginatedData, TableMeta } from '@/types';

type Property = { id: number; name: string };
type RoleOption = { value: string; label: string };
type UserRole = { name: string; label: string };
type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: UserRole[];
    role: string | null;
    properties: Property[];
    is_active: boolean;
    status: 'active' | 'invited' | 'disabled';
    invited_at: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
};

type PageProps = {
    users: PaginatedData<ManagedUser>;
    properties: Property[];
    roles: RoleOption[];
    search?: string;
    role?: string;
    status?: string;
    sort?: string;
    per_page?: number;
    table: TableMeta;
};

function StatusBadge({ user }: { user: ManagedUser }) {
    if (user.status === 'invited') {
        return <Badge variant="outline">Invited</Badge>;
    }

    if (user.status === 'active') {
        return <Badge className="bg-green-600">Active</Badge>;
    }

    return <Badge variant="secondary">Disabled</Badge>;
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
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [viewingUser, setViewingUser] = useState<ManagedUser | null>(null);
    const [formKey, setFormKey] = useState(0);
    const [confirmState, setConfirmState] = useState<{
        user: ManagedUser;
        action: 'disable' | 'reset' | 'resend';
    } | null>(null);

    const table = useTable({
        routeFn: () => users.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            role: currentRole,
            status: currentStatus,
        },
        defaults: {
            sort: 'name',
            per_page: '15',
        },
    });

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

    function disableAccess(user: ManagedUser) {
        setConfirmState({ user, action: 'disable' });
    }

    function sendReset(user: ManagedUser) {
        setConfirmState({ user, action: 'reset' });
    }

    function resendInvite(user: ManagedUser) {
        setConfirmState({ user, action: 'resend' });
    }

    function executeConfirmed() {
        if (!confirmState) {
            return;
        }

        const { user, action } = confirmState;

        if (action === 'disable') {
            router.delete(destroy.url(user));
        } else if (action === 'reset') {
            router.post(resetPassword.url(user));
        } else {
            router.post(resendInvitation.url(user));
        }

        setConfirmState(null);
    }

    const columns: TableColumn<ManagedUser>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'email',
            label: 'Email',
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'last_login_at',
            label: 'Last Login',
            sortable: true,
            className: 'text-muted-foreground',
            render: (u) => formatDate(u.last_login_at),
        },
        {
            key: '_roles',
            label: 'Roles',
            render: (u) =>
                u.roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {u.roles.map((r) => (
                            <Badge key={r.name} variant="outline">
                                {r.label}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        No roles
                    </span>
                ),
        },
        {
            key: '_properties',
            label: 'Assigned Properties',
            className: 'text-muted-foreground',
            render: (u) =>
                u.properties.length > 0
                    ? u.properties.map((p) => p.name).join(', ')
                    : u.role === 'owner'
                      ? 'All properties'
                      : 'No properties',
        },
        {
            key: '_status',
            label: 'Status',
            render: (u) => <StatusBadge user={u} />,
        },
        {
            key: '_actions',
            label: '',
            render: (u) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(event) => event.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <DropdownMenuItem
                            onClick={() => router.visit(`/users/${u.id}`)}
                        >
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(u)}>
                            <Pencil className="size-4" />
                            Edit / Assign Property
                        </DropdownMenuItem>
                        {u.status === 'active' && (
                            <DropdownMenuItem onClick={() => sendReset(u)}>
                                <KeyRound className="size-4" />
                                Reset Password
                            </DropdownMenuItem>
                        )}
                        {u.status === 'invited' && (
                            <DropdownMenuItem onClick={() => resendInvite(u)}>
                                <UserPlus className="size-4" />
                                Resend Invite Link
                            </DropdownMenuItem>
                        )}
                        {u.status !== 'disabled' && (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => disableAccess(u)}
                            >
                                <ShieldOff className="size-4" />
                                Disable Access
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Invite staff and manage access"
                    />
                    <Button onClick={openInvite}>
                        <UserPlus className="size-4" />
                        Invite User
                    </Button>
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
                            placeholder="Search by name or email..."
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
                    noun="users"
                    empty={{
                        message: 'No users yet.',
                        createLabel: 'Invite a user',
                        onCreate: openInvite,
                    }}
                />
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
            />
            <Dialog
                open={confirmState !== null}
                onOpenChange={() => setConfirmState(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmState?.action === 'disable'
                                ? 'Disable access'
                                : confirmState?.action === 'reset'
                                  ? 'Reset password'
                                  : 'Resend invitation'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmState?.action === 'disable' && (
                                <>Disable access for <span className="font-medium">{confirmState.user.name}</span>?</>
                            )}
                            {confirmState?.action === 'reset' && (
                                <>Send password reset to <span className="font-medium">{confirmState.user.email}</span>?</>
                            )}
                            {confirmState?.action === 'resend' && (
                                <>Resend invitation to <span className="font-medium">{confirmState.user.email}</span>?</>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmState(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant={confirmState?.action === 'disable' ? 'destructive' : 'default'}
                            onClick={executeConfirmed}
                        >
                            {confirmState?.action === 'disable'
                                ? 'Disable'
                                : confirmState?.action === 'reset'
                                  ? 'Send Reset'
                                  : 'Resend'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
    const [selectedRoles, setSelectedRoles] = useState<string[]>(
        () => user?.roles.map((r) => r.name) ?? [],
    );

    const previousUserIdRef = useRef(user?.id);

    if (previousUserIdRef.current !== user?.id) {
        previousUserIdRef.current = user?.id;
        setSelectedPropertyIds(
            user?.properties.map((property) => property.id) ?? [],
        );
        setSelectedRoles(user?.roles.map((r) => r.name) ?? []);
    }

    const canEditRole = user?.role !== 'owner';
    const formProps = isEdit
        ? { action: update.url(user!), method: 'put' as const }
        : { action: store.url(), method: 'post' as const };

    function toggleProperty(propertyId: number, checked: boolean) {
        setSelectedPropertyIds((current) =>
            checked
                ? [...current, propertyId]
                : current.filter((id) => id !== propertyId),
        );
    }

    function toggleRole(roleName: string, checked: boolean) {
        setSelectedRoles((current) =>
            checked
                ? [...current, roleName]
                : current.filter((r) => r !== roleName),
        );
    }

    return (
        <Sheet key={user?.id ?? 'new'} open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isEdit ? 'Edit User' : 'Invite User'}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update access and property assignments'
                            : 'Invite a team member'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form {...formProps} onSuccess={() => onOpenChange(false)}>
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user?.name ?? ''}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={user?.email ?? ''}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-3">
                                    <Label>Roles</Label>
                                    <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border p-3">
                                        {canEditRole ? (
                                            roles.map((role) => (
                                                <label
                                                    key={role.value}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={selectedRoles.includes(
                                                            role.value,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleRole(
                                                                role.value,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {role.label}
                                                </label>
                                            ))
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Owner
                                            </p>
                                        )}
                                    </div>
                                    {canEditRole &&
                                        selectedRoles.map((roleName) => (
                                            <input
                                                key={roleName}
                                                type="hidden"
                                                name="roles[]"
                                                value={roleName}
                                            />
                                        ))}
                                    <InputError message={errors.roles} />
                                </div>

                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={
                                        user?.is_active === false ? '0' : '1'
                                    }
                                />
                                {selectedPropertyIds.map((propertyId) => (
                                    <input
                                        key={propertyId}
                                        type="hidden"
                                        name="property_ids[]"
                                        value={propertyId}
                                    />
                                ))}

                                <div className="grid gap-3">
                                    <Label>Assigned Properties</Label>
                                    <div className="max-h-56 space-y-2 overflow-y-auto rounded-lg border p-3">
                                        {properties.map((property) => (
                                            <label
                                                key={property.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={selectedPropertyIds.includes(
                                                        property.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleProperty(
                                                            property.id,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                {property.name}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError message={errors.property_ids} />
                                </div>

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={() => onOpenChange(false)}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        {isEdit ? 'Save' : 'Send Invite'}
                                    </Button>
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
}: {
    user: ManagedUser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: (user: ManagedUser) => void;
    onDisable: (user: ManagedUser) => void;
    onResetPassword: (user: ManagedUser) => void;
    onResendInvitation: (user: ManagedUser) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{user?.name ?? 'User'}</SheetTitle>
                    <SheetDescription>{user?.email}</SheetDescription>
                </SheetHeader>

                {user && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Status
                                </h3>
                                <StatusBadge user={user} />
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Account
                                </h3>
                                <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Name
                                        </span>
                                        <span className="text-sm font-medium">
                                            {user.name}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Email
                                        </span>
                                        <span className="text-sm">
                                            {user.email}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Roles
                                        </span>
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length > 0 ? (
                                                user.roles.map((r) => (
                                                    <Badge
                                                        key={r.name}
                                                        variant="outline"
                                                    >
                                                        {r.label}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    None
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Email verified
                                        </span>
                                        <span className="text-sm">
                                            {user.email_verified_at
                                                ? 'Yes'
                                                : 'No'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Last login
                                        </span>
                                        <span className="text-sm tabular-nums">
                                            {formatDate(user.last_login_at)}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Properties
                                </h3>
                                <div className="rounded-lg border p-4">
                                    <p className="text-sm text-muted-foreground">
                                        {user.properties.length > 0
                                            ? user.properties
                                                  .map(
                                                      (property) =>
                                                          property.name,
                                                  )
                                                  .join(', ')
                                            : user.role === 'owner'
                                              ? 'All properties'
                                              : 'No properties assigned'}
                                    </p>
                                </div>
                            </section>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {user.status === 'invited' && (
                                <Button
                                    variant="outline"
                                    onClick={() => onResendInvitation(user)}
                                >
                                    <UserPlus className="size-4" />
                                    Resend Invite Link
                                </Button>
                            )}

                            <Button
                                variant="outline"
                                onClick={() => onEdit(user)}
                            >
                                <Pencil className="size-4" />
                                Edit / Assign Property
                            </Button>

                            {user.status === 'active' && (
                                <Button
                                    variant="outline"
                                    onClick={() => onResetPassword(user)}
                                >
                                    <KeyRound className="size-4" />
                                    Reset Password
                                </Button>
                            )}

                            {user.status !== 'disabled' && (
                                <Button
                                    variant="destructive"
                                    onClick={() => onDisable(user)}
                                >
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
