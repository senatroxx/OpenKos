import { router, usePage } from '@inertiajs/react';
import { Check, Pencil, Trash2, UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { MaintenanceTicket } from '@/types';

const statusLabel: Record<string, string> = {
    reported: 'Reported',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

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
    onEdit,
}: {
    ticket: MaintenanceTicket | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canUpdate: boolean;
    canDelete: boolean;
    onEdit?: () => void;
    onResolve?: () => void;
}) {
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    if (! ticket) {
return null;
}

    const roleLabel = (name: string | undefined, roles?: { name: string; label?: string }[]) => {
        const displayName = name ?? '—';
        const role = roles?.[0];

        if (role) {
            return `${displayName} — ${role.label ?? role.name}`;
        }

        return displayName;
    };

    const handleStatusChange = (status: string) => {
        if (status === 'resolved' && onResolve) {
            onResolve();

            return;
        }
        router.put(maintenanceTickets.update.url(ticket.id), { status });
    };

    const handleAssignToMe = () => {
        router.post(maintenanceTickets.assign.url(ticket.id), {
            assigned_to: auth.user.id,
        });
    };

    const handleDelete = () => {
        if (confirm('Delete this ticket?')) {
            router.delete(maintenanceTickets.destroy.url(ticket.id));
            onOpenChange(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{ticket.title}</SheetTitle>
                    <SheetDescription>
                        {ticket.reference ?? `#${ticket.id}`} &middot; {statusLabel[ticket.status] ?? ticket.status}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex h-full flex-col px-4">
                    <div className="flex-1 overflow-y-auto pt-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">Property</p>
                                    <p className="text-sm">{ticket.property?.name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Location</p>
                                    <p className="text-sm">{ticket.room?.name ?? ticket.location ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Priority</p>
                                    <p className="text-sm">{priorityLabel[ticket.priority] ?? ticket.priority}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Assigned To</p>
                                    <p className="text-sm">{roleLabel(ticket.assignee?.name, ticket.assignee?.roles)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Created By</p>
                                    <p className="text-sm">{roleLabel(ticket.creator?.name, ticket.creator?.roles)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Created At</p>
                                    <p className="text-sm">{new Date(ticket.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>

                            {ticket.description && (
                                <div>
                                    <p className="text-xs text-muted-foreground">Description</p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">{ticket.description}</p>
                                </div>
                            )}

                            {ticket.resolution_notes && (
                                <div>
                                    <p className="text-xs text-muted-foreground">Resolution</p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">{ticket.resolution_notes}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2 border-t py-3 [&_button]:cursor-pointer">
                        {canUpdate && (
                            <Button size="sm" variant="outline" onClick={onEdit}>
                                <Pencil className="size-3.5" />
                                <span className="ml-1">Edit</span>
                            </Button>
                        )}
                        {ticket.status === 'reported' && canUpdate && (
                            <Button size="sm" onClick={() => handleStatusChange('in_progress')}>Start</Button>
                        )}
                        {ticket.status === 'in_progress' && canUpdate && (
                            <Button size="sm" onClick={() => handleStatusChange('resolved')}>
                                <Check className="size-3.5" />
                                <span className="ml-1">Resolve</span>
                            </Button>
                        )}
                        {(ticket.status === 'reported' || ticket.status === 'in_progress') && canUpdate && (
                            <Button size="sm" variant="outline" onClick={() => handleStatusChange('cancelled')}>Cancel</Button>
                        )}
                        {canUpdate && (
                            <Button size="sm" variant="outline" onClick={handleAssignToMe}>
                                <UserPlus className="size-3.5" />
                                <span className="ml-1">Assign to me</span>
                            </Button>
                        )}
                        {canDelete && (
                            <Button size="sm" variant="destructive" onClick={handleDelete}>
                                <Trash2 className="size-3.5" />
                            </Button>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
