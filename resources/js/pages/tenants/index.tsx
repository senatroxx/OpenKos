import { Head, router, usePage } from '@inertiajs/react';
import {
    DoorOpen,
    EllipsisVertical,
    ExternalLink,
    Eye,
    MailPlus,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    AssignUnitSheet,
    MoveOutSheet,
    TenantDetailSheet,
    TenantDocumentsSheet,
    TenantFormSheet,
} from '@/components/features';
import InviteToAppSheet from '@/components/features/tenants/invite-to-app-sheet';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { appAccessStatus, inviteActionLabel } from '@/lib/app-access';
import { t } from '@/lib/i18n';
import tenants from '@/routes/tenants';
import type {
    Auth,
    AvailableUnit,
    Lease,
    PaginatedData,
    TableMeta,
    WorkspaceTenant,
} from '@/types';

type PageProps = {
    tenants: PaginatedData<WorkspaceTenant>;
    availableUnits: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export default function Index({
    tenants: data,
    availableUnits: _availableUnits,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.permissions;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTenant, setEditingTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingTenant, setViewingTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [assignUnitOpen, setAssignUnitOpen] = useState(false);
    const [assignTenant, setAssignTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [moveOutTenant, setMoveOutTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [documentsOpen, setDocumentsOpen] = useState(false);
    const [documentsTenant, setDocumentsTenant] =
        useState<WorkspaceTenant | null>(null);

    const [archiveConfirm, setArchiveConfirm] =
        useState<WorkspaceTenant | null>(null);

    const [disableConfirm, setDisableConfirm] =
        useState<WorkspaceTenant | null>(null);

    const [inviteTenant, setInviteTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const table = useTable({
        routeFn: () => tenants.index(),
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
        setEditingTenant(null);
        setDialogOpen(true);
    }

    function openEdit(tenant: WorkspaceTenant) {
        setEditingTenant(tenant);
        setDialogOpen(true);
    }

    function openDetail(tenant: WorkspaceTenant) {
        setViewingTenant(tenant);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingTenant) {
            return;
        }

        setEditingTenant(viewingTenant);
        setDetailOpen(false);
        setDialogOpen(true);
    }

    function openAssignUnit() {
        if (!viewingTenant) {
            return;
        }

        setAssignTenant(viewingTenant);
        setDetailOpen(false);
        setAssignUnitOpen(true);
    }

    function openMoveOut() {
        if (!viewingTenant) {
            return;
        }

        setMoveOutTenant(viewingTenant);
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openDocuments() {
        if (!viewingTenant) {
            return;
        }

        setDocumentsTenant(viewingTenant);
        setDetailOpen(false);
        setDocumentsOpen(true);
    }

    function archive(tenant: WorkspaceTenant) {
        setArchiveConfirm(tenant);
    }

    function confirmArchive() {
        if (!archiveConfirm) {
            return;
        }

        router.delete(tenants.destroy.url(archiveConfirm));
        setArchiveConfirm(null);
    }

    function restore(tenant: WorkspaceTenant) {
        router.post(tenants.restore.url(tenant));
    }

    function confirmDisable() {
        if (!disableConfirm) {
            return;
        }

        router.post(tenants.disableAccess(disableConfirm.id).url);
        setDisableConfirm(null);
    }

    function resendInvitation(tenant: WorkspaceTenant) {
        router.post(tenants.resendInvitation(tenant.id).url);
    }

    const columns: TableColumn<WorkspaceTenant>[] = [
        {
            key: 'name',
            label: t('Name'),
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'phone',
            label: t('Phone'),
            sortable: true,
            className: 'text-muted-foreground',
            render: (tenantRow) => tenantRow.phone ?? '\u2014',
        },
        {
            key: '_lease',
            label: t('Lease'),
            render: (tenantRow) =>
                (tenantRow.active_leases_count ?? 0) > 0 ? (
                    <StatusBadge status="active" />
                ) : (
                    <Badge variant="outline">{t('None')}</Badge>
                ),
        },
        {
            key: '_status',
            label: t('Status'),
            render: (tenantRow) => {
                const status = tenantRow.deleted_at
                    ? 'archived'
                    : tenantRow.is_active
                      ? 'active'
                      : 'inactive';

                return <StatusBadge domain="tenant" value={status} />;
            },
        },
        {
            key: '_app_access',
            label: t('App Access'),
            render: (tenantRow) => {
                const status = appAccessStatus(tenantRow.user);

                return status === 'none' ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <StatusBadge domain="app_access" value={status} />
                );
            },
        },
        {
            key: '_actions',
            label: '',
            render: (tenantRow) => (
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
                            onClick={() =>
                                router.get(tenants.show.url(tenantRow))
                            }
                        >
                            <ExternalLink className="size-4" />
                            {t('Open Workspace')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(tenantRow)}>
                            <Eye className="size-4" />
                            {t('View')}
                        </DropdownMenuItem>
                        {!tenantRow.deleted_at &&
                            tenantRow.active_leases_count === 0 && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setAssignTenant(tenantRow);
                                        setAssignUnitOpen(true);
                                    }}
                                >
                                    <DoorOpen className="size-4" />
                                    {t('Assign to Unit')}
                                </DropdownMenuItem>
                            )}
                        {!tenantRow.deleted_at && (
                            <DropdownMenuItem
                                onClick={() => openEdit(tenantRow)}
                            >
                                <Pencil className="size-4" />
                                {t('Edit')}
                            </DropdownMenuItem>
                        )}

                        {!tenantRow.deleted_at && <DropdownMenuSeparator />}

                        {permissions.includes('tenants.invite') &&
                            !tenantRow.deleted_at &&
                            !tenantRow.user_id && (
                                <DropdownMenuItem
                                    onClick={() => setInviteTenant(tenantRow)}
                                >
                                    <MailPlus className="size-4" />
                                    {t('Invite to App')}
                                </DropdownMenuItem>
                            )}
                        {permissions.includes('tenants.invite') &&
                            !tenantRow.deleted_at &&
                            inviteActionLabel(
                                appAccessStatus(tenantRow.user),
                            ) && (
                                <DropdownMenuItem
                                    onClick={() => resendInvitation(tenantRow)}
                                >
                                    <Send className="size-4" />
                                    {t(
                                        inviteActionLabel(
                                            appAccessStatus(tenantRow.user),
                                        ) ?? '',
                                    )}
                                </DropdownMenuItem>
                            )}
                        {permissions.includes('tenants.invite') &&
                            !tenantRow.deleted_at &&
                            ['invited', 'active'].includes(
                                appAccessStatus(tenantRow.user),
                            ) && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setDisableConfirm(tenantRow)}
                                >
                                    <UserX className="size-4" />
                                    {t('Disable Access')}
                                </DropdownMenuItem>
                            )}

                        <DropdownMenuSeparator />

                        {tenantRow.deleted_at ? (
                            <DropdownMenuItem
                                onClick={() => restore(tenantRow)}
                            >
                                <RotateCcw className="size-4" />
                                {t('Restore')}
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => archive(tenantRow)}
                            >
                                <Trash2 className="size-4" />
                                {t('Archive')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Tenants')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('Tenants')}
                        description={t('Manage your tenants')}
                    />

                    <Button onClick={openCreate}>{t('New Tenant')}</Button>
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
                                'Search by name, phone, or ID card...',
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
                    noun={t('tenants')}
                    empty={{
                        message: t('No tenants yet.'),
                        createLabel: t('Create your first tenant'),
                        onCreate: openCreate,
                    }}
                />
            </div>

            <TenantDetailSheet
                tenant={viewingTenant}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onAssignToUnit={openAssignUnit}
                onMoveOut={openMoveOut}
                onDocuments={openDocuments}
                onInvite={
                    viewingTenant?.user_id ||
                    !permissions.includes('tenants.invite')
                        ? undefined
                        : () => {
                              if (viewingTenant) {
                                  setDetailOpen(false);
                                  setInviteTenant(viewingTenant);
                              }
                          }
                }
                onResend={
                    viewingTenant && permissions.includes('tenants.invite')
                        ? () => resendInvitation(viewingTenant)
                        : undefined
                }
                onDisableAccess={
                    viewingTenant && permissions.includes('tenants.invite')
                        ? () => {
                              setDetailOpen(false);
                              setDisableConfirm(viewingTenant);
                          }
                        : undefined
                }
            />

            <TenantFormSheet
                key={editingTenant?.id ?? 'new'}
                tenant={editingTenant}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignTenant && (
                <AssignUnitSheet
                    key={assignTenant.id}
                    tenant={assignTenant}
                    availableUnits={_availableUnits}
                    open={assignUnitOpen}
                    onOpenChange={setAssignUnitOpen}
                />
            )}

            <TenantDocumentsSheet
                tenant={documentsTenant}
                open={documentsOpen}
                onOpenChange={setDocumentsOpen}
            />

            <MoveOutSheet
                lease={
                    moveOutTenant
                        ? {
                              id:
                                  (
                                      moveOutTenant as WorkspaceTenant & {
                                          leases?: Lease[];
                                      }
                                  ).leases?.[0]?.id ?? 0,
                              tenants: [
                                  {
                                      id: moveOutTenant.id,
                                      name: moveOutTenant.name,
                                      phone: moveOutTenant.phone,
                                      pivot: { is_primary: true },
                                  },
                              ],
                              primary_tenant: {
                                  id: moveOutTenant.id,
                                  name: moveOutTenant.name,
                                  phone: moveOutTenant.phone,
                              },
                              unit:
                                  (
                                      moveOutTenant as WorkspaceTenant & {
                                          leases?: Lease[];
                                      }
                                  ).leases?.[0]?.unit ?? null,
                          }
                        : null
                }
                availableUnits={_availableUnits}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />

            <InviteToAppSheet
                tenantId={inviteTenant?.id ?? null}
                open={inviteTenant !== null}
                onOpenChange={(open) => !open && setInviteTenant(null)}
            />

            <Dialog
                open={archiveConfirm !== null}
                onOpenChange={() => setArchiveConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Archive tenant')}</DialogTitle>
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

            <Dialog
                open={disableConfirm !== null}
                onOpenChange={() => setDisableConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Disable app access')}</DialogTitle>
                        <DialogDescription>
                            {t('This signs')}{' '}
                            <span className="font-medium">
                                {disableConfirm?.name}
                            </span>{' '}
                            {t(
                                "out and revokes their portal access. They'll still receive notifications, and you can re-invite them later.",
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDisableConfirm(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDisable}>
                            {t('Disable Access')}
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
            title: 'Tenants',
            href: tenants.index(),
        },
    ],
};
