import { Head, router, useForm } from '@inertiajs/react';
import {
    EllipsisVertical,
    Eye,
    KeyRound,
    Pencil,
    ShieldOff,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { Heading, InputError } from '@/components/shared';
import { StatusBadge as SharedStatusBadge } from '@/components/shared/status-badge';
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
import { formatDateTime } from '@/lib/formatters';
import { t } from '@/lib/i18n';
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
    return <SharedStatusBadge domain="user" value={user.status} />;
}

function formatLastLogin(value: string | null) {
    if (!value) {
        return t('Never');
    }

    return formatDateTime(value);
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
        setFormOpen(true);
    }

    function openEdit(user: ManagedUser) {
        setEditingUser(user);
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
            label: t('Name'),
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'email',
            label: t('Email'),
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'last_login_at',
            label: t('Last Login'),
            sortable: true,
            className: 'text-muted-foreground',
            render: (u) => formatLastLogin(u.last_login_at),
        },
        {
            key: '_roles',
            label: t('Roles'),
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
                        {t('No roles')}
                    </span>
                ),
        },
        {
            key: '_properties',
            label: t('Assigned Properties'),
            className: 'text-muted-foreground',
            render: (u) =>
                u.properties.length > 0
                    ? u.properties.map((p) => p.name).join(', ')
                    : u.role === 'owner'
                      ? t('All properties')
                      : t('No properties'),
        },
        {
            key: '_status',
            label: t('Status'),
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
                            {t('View')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(u)}>
                            <Pencil className="size-4" />
                            {t('Edit / Assign Property')}
                        </DropdownMenuItem>
                        {u.status === 'active' && (
                            <DropdownMenuItem onClick={() => sendReset(u)}>
                                <KeyRound className="size-4" />
                                {t('Reset Password')}
                            </DropdownMenuItem>
                        )}
                        {u.status === 'invited' && (
                            <DropdownMenuItem onClick={() => resendInvite(u)}>
                                <UserPlus className="size-4" />
                                {t('Resend Invite Link')}
                            </DropdownMenuItem>
                        )}
                        {u.status !== 'disabled' && (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => disableAccess(u)}
                            >
                                <ShieldOff className="size-4" />
                                {t('Disable Access')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Users')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('Users')}
                        description={t('Invite staff and manage access')}
                    />
                    <Button onClick={openInvite}>
                        <UserPlus className="size-4" />
                        {t('Invite User')}
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
                            placeholder={t('Search by name or email...')}
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
                    noun={t('users')}
                    empty={{
                        message: t('No users yet.'),
                        createLabel: t('Invite a user'),
                        onCreate: openInvite,
                    }}
                />
            </div>

            <UserFormSheet
                key={editingUser?.id ?? 'new'}
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
                                ? t('Disable access')
                                : confirmState?.action === 'reset'
                                  ? t('Reset password')
                                  : t('Resend invitation')}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmState?.action === 'disable' && (
                                <>
                                    {t('Disable access for')}{' '}
                                    <span className="font-medium">
                                        {confirmState.user.name}
                                    </span>
                                    ?
                                </>
                            )}
                            {confirmState?.action === 'reset' && (
                                <>
                                    {t('Send password reset to')}{' '}
                                    <span className="font-medium">
                                        {confirmState.user.email}
                                    </span>
                                    ?
                                </>
                            )}
                            {confirmState?.action === 'resend' && (
                                <>
                                    {t('Resend invitation to')}{' '}
                                    <span className="font-medium">
                                        {confirmState.user.email}
                                    </span>
                                    ?
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmState(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant={
                                confirmState?.action === 'disable'
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={executeConfirmed}
                        >
                            {confirmState?.action === 'disable'
                                ? t('Disable')
                                : confirmState?.action === 'reset'
                                  ? t('Send Reset')
                                  : t('Resend')}
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
    const canEditRole = user?.role !== 'owner';

    const { data, setData, transform, submit, reset, processing, errors } =
        useForm({
            name: user?.name ?? '',
            email: user?.email ?? '',
            roles: user?.roles.map((r) => r.name) ?? [],
            property_ids: user?.properties.map((property) => property.id) ?? [],
            is_active: user?.is_active !== false,
        });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => ({ ...d, is_active: d.is_active ? '1' : '0' }));
        submit(isEdit ? update(user!) : store(), {
            onSuccess: () => handleOpenChange(false),
        });
    }

    function toggleProperty(propertyId: number, checked: boolean) {
        setData((prev) => ({
            ...prev,
            property_ids: checked
                ? [...prev.property_ids, propertyId]
                : prev.property_ids.filter((id) => id !== propertyId),
        }));
    }

    function toggleRole(roleName: string, checked: boolean) {
        setData((prev) => ({
            ...prev,
            roles: checked
                ? [...prev.roles, roleName]
                : prev.roles.filter((r) => r !== roleName),
        }));
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {t(isEdit ? 'Edit User' : 'Invite User')}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? t('Update access and property assignments')
                            : t('Invite a team member')}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit} className="space-y-6 pt-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('Email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-3">
                            <Label>{t('Roles')}</Label>
                            <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border p-3">
                                {canEditRole ? (
                                    roles.map((role) => (
                                        <label
                                            key={role.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={data.roles.includes(
                                                    role.value,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleRole(
                                                        role.value,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            {role.label}
                                        </label>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('Owner')}
                                    </p>
                                )}
                            </div>
                            <InputError message={errors.roles} />
                        </div>

                        <div className="grid gap-3">
                            <Label>{t('Assigned Properties')}</Label>
                            <div className="max-h-56 space-y-2 overflow-y-auto rounded-lg border p-3">
                                {properties.map((property) => (
                                    <label
                                        key={property.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={data.property_ids.includes(
                                                property.id,
                                            )}
                                            onCheckedChange={(checked) =>
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
                                onClick={() => handleOpenChange(false)}
                                disabled={processing}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button disabled={processing}>
                                {t(isEdit ? 'Save' : 'Send Invite')}
                            </Button>
                        </div>
                    </form>
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
                    <SheetTitle>{user?.name ?? t('User')}</SheetTitle>
                    <SheetDescription>{user?.email}</SheetDescription>
                </SheetHeader>

                {user && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Status')}
                                </h3>
                                <StatusBadge user={user} />
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Account')}
                                </h3>
                                <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            {t('Name')}
                                        </span>
                                        <span className="text-sm font-medium">
                                            {user.name}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            {t('Email')}
                                        </span>
                                        <span className="text-sm">
                                            {user.email}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            {t('Roles')}
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
                                                    {t('None')}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            {t('Email verified')}
                                        </span>
                                        <span className="text-sm">
                                            {user.email_verified_at
                                                ? t('Yes')
                                                : t('No')}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            {t('Last login')}
                                        </span>
                                        <span className="text-sm tabular-nums">
                                            {formatLastLogin(
                                                user.last_login_at,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Properties')}
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
                                              ? t('All properties')
                                              : t('No properties assigned')}
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
                                    {t('Resend Invite Link')}
                                </Button>
                            )}

                            <Button
                                variant="outline"
                                onClick={() => onEdit(user)}
                            >
                                <Pencil className="size-4" />
                                {t('Edit / Assign Property')}
                            </Button>

                            {user.status === 'active' && (
                                <Button
                                    variant="outline"
                                    onClick={() => onResetPassword(user)}
                                >
                                    <KeyRound className="size-4" />
                                    {t('Reset Password')}
                                </Button>
                            )}

                            {user.status !== 'disabled' && (
                                <Button
                                    variant="destructive"
                                    onClick={() => onDisable(user)}
                                >
                                    <ShieldOff className="size-4" />
                                    {t('Disable Access')}
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
