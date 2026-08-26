import { Head, router, usePage } from '@inertiajs/react';
import {
    Ban,
    Check,
    EllipsisVertical,
    Eye,
    Pencil,
    Play,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { TicketDetailSheet, TicketFormSheet } from '@/components/features';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTable } from '@/hooks/use-table';
import { formatDate } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { PaginatedData, TableFilterMeta } from '@/types';
import type { MaintenanceTicket } from '@/types';

const priorityColors: Record<string, string> = {
    low: 'bg-muted text-muted-foreground border-border',
    medium: 'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
    high: 'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
    urgent: 'bg-surface-red/70 text-surface-red-foreground border-surface-red-border/80',
};

const priorityLabel: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

export default function Index({
    tickets: data,
    properties,
    units,
    users,
    can,
    table: tableMeta,
    sort: currentSort = '-created_at',
    search: currentSearch = '',
    per_page: currentPerPage = '15',
    status: currentStatus = '',
    priority: currentPriority = '',
    property_id: currentPropertyId = '',
}: {
    tickets: PaginatedData<MaintenanceTicket>;
    properties: { id: number; name: string }[];
    units: {
        id: number;
        name: string;
        property_id: number;
        status: string;
        active_lease_count: number;
        has_maintenance_transfer?: number;
        leases?: { tenants: { id: number; name: string }[] }[];
    }[];
    users: {
        id: number;
        name: string;
        roles?: { name: string; label?: string }[];
    }[];
    can: { create: boolean; update: boolean; delete: boolean; assign: boolean };
    table: { filters: TableFilterMeta[] };
    sort?: string;
    search?: string;
    per_page?: number | string;
    status?: string;
    priority?: string;
    property_id?: string;
}) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingTicket, setEditingTicket] =
        useState<MaintenanceTicket | null>(null);
    const [detailTicket, setDetailTicket] = useState<MaintenanceTicket | null>(
        null,
    );
    const [resolveTicket, setResolveTicket] =
        useState<MaintenanceTicket | null>(null);
    const [deleteConfirm, setDeleteConfirm] =
        useState<MaintenanceTicket | null>(null);
    const [assignTicket, setAssignTicket] = useState<MaintenanceTicket | null>(
        null,
    );
    const [assigneeId, setAssigneeId] = useState('');
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    const table = useTable({
        routeFn: () => ({ url: maintenanceTickets.index.url() }),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
            priority: currentPriority,
            property_id: currentPropertyId,
        },
        defaults: {
            sort: '-created_at',
            per_page: '15',
        },
    });

    const handleStatusChange = (ticket: MaintenanceTicket, status: string) => {
        if (status === 'resolved') {
            const unit = units.find((r) => r.id === ticket.unit_id);

            if (unit?.has_maintenance_transfer) {
                setResolveTicket(ticket);

                return;
            }
        }

        router.put(maintenanceTickets.update.url(ticket.id), { status });
    };

    const handleAssignToMe = (ticket: MaintenanceTicket) => {
        router.post(maintenanceTickets.assign.url(ticket.id), {
            assigned_to: auth.user.id,
        });
    };

    const columns: TableColumn<MaintenanceTicket>[] = [
        {
            key: 'reference',
            label: t('ID'),
            className: 'text-muted-foreground text-xs font-mono',
            render: (ticket) => ticket.reference ?? `#${ticket.id}`,
        },
        { key: 'title', label: t('Title'), sortable: true },
        {
            key: 'property_name',
            label: t('Property'),
            sortable: true,
            render: (ticket) => ticket.property?.name ?? '—',
        },
        {
            key: 'location',
            label: t('Location'),
            render: (ticket) => ticket.unit?.name ?? ticket.location ?? '—',
        },
        {
            key: 'priority',
            label: t('Priority'),
            sortable: true,
            render: (ticket) => (
                <Badge className={priorityColors[ticket.priority] ?? ''}>
                    {t(priorityLabel[ticket.priority] ?? ticket.priority)}
                </Badge>
            ),
        },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (ticket) => (
                <StatusBadge domain="maintenance" value={ticket.status} />
            ),
        },
        {
            key: 'assigned_to',
            label: t('Assigned To'),
            render: (ticket) => ticket.assignee?.name ?? '—',
        },
        {
            key: 'created_at',
            label: t('Created'),
            sortable: true,
            render: (ticket) => formatDate(ticket.created_at),
        },
        {
            key: 'actions',
            label: '',
            render: (ticket) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <DropdownMenuItem
                            onClick={() =>
                                router.visit(
                                    `/maintenance-tickets/${ticket.id}`,
                                )
                            }
                        >
                            <Eye className="size-4" />
                            {t('Open')}
                        </DropdownMenuItem>
                        {ticket.status === 'reported' && can.update && (
                            <DropdownMenuItem
                                onClick={() =>
                                    handleStatusChange(ticket, 'in_progress')
                                }
                            >
                                <Play className="size-4" />
                                {t('Start')}
                            </DropdownMenuItem>
                        )}
                        {ticket.status === 'in_progress' && can.update && (
                            <DropdownMenuItem
                                onClick={() =>
                                    handleStatusChange(ticket, 'resolved')
                                }
                            >
                                <Check className="size-4" />
                                {t('Resolve')}
                            </DropdownMenuItem>
                        )}
                        {(ticket.status === 'reported' ||
                            ticket.status === 'in_progress') &&
                            can.update && (
                                <DropdownMenuItem
                                    onClick={() =>
                                        handleStatusChange(ticket, 'cancelled')
                                    }
                                >
                                    <Ban className="size-4" />
                                    {t('Cancel')}
                                </DropdownMenuItem>
                            )}
                        {can.update && <DropdownMenuSeparator />}
                        {can.update && (
                            <DropdownMenuItem
                                onClick={() => handleAssignToMe(ticket)}
                            >
                                <UserPlus className="size-4" />
                                {t('Assign to me')}
                            </DropdownMenuItem>
                        )}
                        {can.assign && (
                            <DropdownMenuItem
                                onClick={() => {
                                    setAssignTicket(ticket);
                                    setAssigneeId('');
                                }}
                            >
                                <UserPlus className="size-4" />
                                {t('Assign to...')}
                            </DropdownMenuItem>
                        )}
                        {(can.update || can.delete) && (
                            <DropdownMenuSeparator />
                        )}
                        {can.update && (
                            <DropdownMenuItem
                                onClick={() => {
                                    setEditingTicket(ticket);
                                    setFormOpen(true);
                                }}
                            >
                                <Pencil className="size-4" />
                                {t('Edit')}
                            </DropdownMenuItem>
                        )}
                        {can.delete && (
                            <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => setDeleteConfirm(ticket)}
                            >
                                <Trash2 className="size-4 text-red-600" />
                                {t('Delete')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Maintenance Tickets')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('Maintenance Tickets')}
                        description={t('Track and manage maintenance issues.')}
                    />
                    {can.create && (
                        <Button
                            onClick={() => {
                                setEditingTicket(null);
                                setFormOpen(true);
                            }}
                        >
                            {t('New Ticket')}
                        </Button>
                    )}
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
                            placeholder={t('Search tickets...')}
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    paginator={data}
                    perPage={Number(currentPerPage)}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    onRowClick={(ticket) => setDetailTicket(ticket)}
                    noun={t('tickets')}
                    empty={{
                        message: t('No maintenance tickets yet.'),
                        createLabel: can.create
                            ? t('Report an issue')
                            : undefined,
                        onCreate: can.create
                            ? () => {
                                  setFormOpen(true);
                              }
                            : undefined,
                    }}
                />

                <TicketFormSheet
                    key={editingTicket?.id ?? 'new'}
                    open={formOpen}
                    onOpenChange={(open) => {
                        setFormOpen(open);

                        if (!open) {
                            setEditingTicket(null);
                        }
                    }}
                    ticket={editingTicket}
                    properties={properties}
                    units={units}
                />

                <TicketDetailSheet
                    ticket={detailTicket}
                    open={detailTicket !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDetailTicket(null);
                        }
                    }}
                    canUpdate={can.update}
                    canDelete={can.delete}
                    canAssign={can.assign}
                    onStatusChange={handleStatusChange}
                    onAssignTo={() => {
                        setAssignTicket(detailTicket);
                        setAssigneeId('');
                    }}
                    onEdit={() => {
                        setEditingTicket(detailTicket);
                        setDetailTicket(null);
                        setFormOpen(true);
                    }}
                />

                <Dialog
                    open={resolveTicket !== null}
                    onOpenChange={() => setResolveTicket(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>{t('Restore Occupant?')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'This unit was vacated for maintenance. Move the occupant back?',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const ticket = resolveTicket;
                                    setResolveTicket(null);

                                    if (ticket) {
                                        router.put(
                                            maintenanceTickets.update.url(
                                                ticket.id,
                                            ),
                                            {
                                                status: 'resolved',
                                                restore_unit: '1',
                                            },
                                        );
                                    }
                                }}
                            >
                                {t('Keep in current unit')}
                            </Button>
                            <Button
                                onClick={() => {
                                    const ticket = resolveTicket;
                                    setResolveTicket(null);

                                    if (ticket) {
                                        router.put(
                                            maintenanceTickets.update.url(
                                                ticket.id,
                                            ),
                                            {
                                                status: 'resolved',
                                                restore_unit: '1',
                                                move_back: '1',
                                            },
                                        );
                                    }
                                }}
                            >
                                {t('Move back')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={deleteConfirm !== null}
                    onOpenChange={() => setDeleteConfirm(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Delete ticket')}</DialogTitle>
                            <DialogDescription>
                                Delete{' '}
                                <span className="font-medium">
                                    {deleteConfirm?.reference ??
                                        `#${deleteConfirm?.id}`}
                                </span>
                                ? {t('This cannot be undone.')}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDeleteConfirm(null)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    const ticket = deleteConfirm;
                                    setDeleteConfirm(null);

                                    if (ticket) {
                                        router.delete(
                                            maintenanceTickets.destroy.url(
                                                ticket.id,
                                            ),
                                        );
                                    }
                                }}
                            >
                                {t('Delete')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={assignTicket !== null}
                    onOpenChange={() => {
                        setAssignTicket(null);
                        setAssigneeId('');
                    }}
                >
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>{t('Assign Ticket')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'Select a staff member to assign this ticket to.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <Select
                                value={assigneeId}
                                onValueChange={setAssigneeId}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue
                                        placeholder={t('Select staff...')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {users
                                        .filter((u) => u.id !== auth.user.id)
                                        .map((u) => {
                                            const role = u.roles?.[0];
                                            const label = role
                                                ? ` — ${role.label ?? role.name}`
                                                : '';

                                            return (
                                                <SelectItem
                                                    key={u.id}
                                                    value={String(u.id)}
                                                >
                                                    {u.name}
                                                    {label}
                                                </SelectItem>
                                            );
                                        })}
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setAssignTicket(null);
                                    setAssigneeId('');
                                }}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                disabled={!assigneeId}
                                onClick={() => {
                                    const ticket = assignTicket;
                                    setAssignTicket(null);
                                    setAssigneeId('');

                                    if (ticket && assigneeId) {
                                        router.post(
                                            maintenanceTickets.assign.url(
                                                ticket.id,
                                            ),
                                            {
                                                assigned_to: Number(assigneeId),
                                            },
                                        );
                                    }
                                }}
                            >
                                {t('Assign')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Maintenance Tickets', href: maintenanceTickets.index() },
    ],
};
