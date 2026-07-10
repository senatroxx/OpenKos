import { router, usePage } from '@inertiajs/react';
import { Check, Pencil, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/shared/status-badge';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate } from '@/lib/formatters';
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { MaintenanceTicket } from '@/types';

const priorityLabel: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

export default function TicketDetailSheet({
    ticket,
    open,
    onOpenChange,
    canUpdate,
    canDelete,
    canAssign,
    onEdit,
    onStatusChange,
    onAssignTo,
}: {
    ticket: MaintenanceTicket | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canAssign?: boolean;
    onEdit?: () => void;
    onStatusChange?: (ticket: MaintenanceTicket, status: string) => void;
    onAssignTo?: () => void;
}) {
    const [deleteConfirm, setDeleteConfirm] = useState(false);
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    if (!ticket) {
        return null;
    }

    const roleLabel = (
        name: string | undefined,
        roles?: { name: string; label?: string }[],
    ) => {
        const displayName = name ?? '—';
        const role = roles?.[0];

        if (role) {
            return `${displayName} — ${role.label ?? role.name}`;
        }

        return displayName;
    };

    const handleStatusChange = (status: string) => {
        if (onStatusChange) {
            onStatusChange(ticket, status);
        }
    };

    const handleAssignToMe = () => {
        router.post(maintenanceTickets.assign.url(ticket.id), {
            assigned_to: auth.user.id,
        });
    };

    const handleDelete = () => {
        setDeleteConfirm(true);
    };

    const confirmDelete = () => {
        router.delete(maintenanceTickets.destroy.url(ticket.id));
        setDeleteConfirm(false);
        onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{ticket.title}</SheetTitle>
                    <SheetDescription>
                        {ticket.reference ?? `#${ticket.id}`} &middot;{' '}
                        <StatusBadge domain="maintenance" value={ticket.status} />
                    </SheetDescription>
                </SheetHeader>

                <div className="flex h-full flex-col px-4">
                    <div className="flex-1 overflow-y-auto pt-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Property
                                    </p>
                                    <p className="text-sm">
                                        {ticket.property?.name ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Location
                                    </p>
                                    <p className="text-sm">
                                        {ticket.unit?.name ??
                                            ticket.location ??
                                            '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Priority
                                    </p>
                                    <p className="text-sm">
                                        {priorityLabel[ticket.priority] ??
                                            ticket.priority}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Assigned To
                                    </p>
                                    <p className="text-sm">
                                        {roleLabel(
                                            ticket.assignee?.name,
                                            ticket.assignee?.roles,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Created By
                                    </p>
                                    <p className="text-sm">
                                        {roleLabel(
                                            ticket.creator?.name,
                                            ticket.creator?.roles,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Created At
                                    </p>
                                    <p className="text-sm">
                                        {formatDate(ticket.created_at)}
                                    </p>
                                </div>
                            </div>

                            {ticket.maintenance_transfer_to && (
                                <div className="rounded-lg border p-3 text-sm">
                                    <span className="text-muted-foreground">
                                        Occupant moved to{' '}
                                    </span>
                                    <span className="font-medium">
                                        {ticket.maintenance_transfer_to}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        during maintenance
                                    </span>
                                </div>
                            )}

                            {ticket.description && (
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Description
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {ticket.description}
                                    </p>
                                </div>
                            )}

                            {ticket.resolution_notes && (
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Resolution
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {ticket.resolution_notes}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 border-t py-3 [&_button]:cursor-pointer">
                        {canUpdate && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onEdit}
                            >
                                <Pencil className="size-3.5" />
                                <span className="ml-1">Edit</span>
                            </Button>
                        )}
                        {ticket.status === 'reported' && canUpdate && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    handleStatusChange('in_progress')
                                }
                            >
                                Start
                            </Button>
                        )}
                        {ticket.status === 'in_progress' && canUpdate && (
                            <Button
                                size="sm"
                                onClick={() => handleStatusChange('resolved')}
                            >
                                <Check className="size-3.5" />
                                <span className="ml-1">Resolve</span>
                            </Button>
                        )}
                        {(ticket.status === 'reported' ||
                            ticket.status === 'in_progress') &&
                            canUpdate && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        handleStatusChange('cancelled')
                                    }
                                >
                                    Cancel
                                </Button>
                            )}
                        {canUpdate && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleAssignToMe}
                            >
                                <UserPlus className="size-3.5" />
                                <span className="ml-1">Assign to me</span>
                            </Button>
                        )}
                        {canAssign && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onAssignTo}
                            >
                                <UserPlus className="size-3.5" />
                                <span className="ml-1">Assign to...</span>
                            </Button>
                        )}
                        {canDelete && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                <Trash2 className="size-3.5" />
                                <span className="ml-1">Delete</span>
                            </Button>
                        )}
                    </div>
                </div>
            </SheetContent>

            <Dialog
                open={deleteConfirm}
                onOpenChange={setDeleteConfirm}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete ticket</DialogTitle>
                        <DialogDescription>
                            Delete this ticket? This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Sheet>
    );
}
