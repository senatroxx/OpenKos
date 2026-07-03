import { Head, Link } from '@inertiajs/react';
import { Heading } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import properties from '@/routes/properties';
import type { MaintenanceTicket, Property, Room } from '@/types';

type PageProps = {
    property: Property;
    room: Room;
    tickets: {
        data: MaintenanceTicket[];
        meta?: {
            current_page: number;
            last_page: number;
            total: number;
        };
    };
};

const priorityColors: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700',
    medium: 'bg-yellow-100 text-yellow-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};

const statusColors: Record<string, string> = {
    reported: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-purple-100 text-purple-700',
    resolved: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const priorityLabel: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

const statusLabel: Record<string, string> = {
    reported: 'Reported',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

function formatPrice(cents: string | null): string {
    if (!cents) return '—';

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(date: string | null): string {
    if (!date) return '—';

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function MaintenanceHistory({ property, room, tickets }: PageProps) {
    const backUrl = properties.rooms.index.url(property);

    return (
        <>
            <Head title={`Maintenance History - ${room.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <div className="mb-1 inline-block text-xs text-muted-foreground">
                        <Link href={backUrl} className="hover:text-foreground">
                            &larr; Back to {property.name} rooms
                        </Link>
                    </div>
                    <Heading
                        title={`${room.name} — Maintenance History`}
                        description={
                            room.floor ? `Floor ${room.floor}` : undefined
                        }
                    />
                </div>

                {tickets.data.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-lg border py-16">
                        <p className="text-muted-foreground">
                            No maintenance history for this room.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">ID</th>
                                    <th className="px-4 py-3 font-medium">Title</th>
                                    <th className="px-4 py-3 font-medium">Priority</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Cost</th>
                                    <th className="px-4 py-3 font-medium">Assigned To</th>
                                    <th className="px-4 py-3 font-medium">Created</th>
                                    <th className="px-4 py-3 font-medium">Resolved</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tickets.data.map((ticket) => (
                                    <tr
                                        key={ticket.id}
                                        className="border-b last:border-0 hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {ticket.reference ?? `#${ticket.id}`}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {ticket.title}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge className={priorityColors[ticket.priority] ?? ''}>
                                                {priorityLabel[ticket.priority] ?? ticket.priority}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge className={statusColors[ticket.status] ?? ''}>
                                                {statusLabel[ticket.status] ?? ticket.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatPrice(ticket.cost)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {ticket.assignee?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {formatDate(ticket.created_at)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {formatDate(ticket.resolved_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

MaintenanceHistory.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
        {
            title: 'Rooms',
        },
    ],
};
