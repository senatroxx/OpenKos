import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/formatters';
import type { MaintenanceTicket } from '@/types';

const STATUS_COLORS: Record<string, string> = {
    reported: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-purple-100 text-purple-700',
    resolved: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const STATUS_LABEL: Record<string, string> = {
    reported: 'Reported',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

export default function MaintenanceTab({ tickets }: { tickets: MaintenanceTicket[] }) {
    if (tickets.length === 0) {
        return <p className="text-sm text-muted-foreground">No maintenance tickets.</p>;
    }

    return (
        <div className="space-y-2">
            {tickets.map((t) => (
                <div key={t.id} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                    <div>
                        <p className="font-medium">{t.title}</p>
                        <p className="text-xs text-muted-foreground">
                            {t.reference ?? `#${t.id}`} · {formatDate(t.created_at)}
                        </p>
                    </div>
                    <Badge className={STATUS_COLORS[t.status] ?? ''}>
                        {STATUS_LABEL[t.status] ?? t.status}
                    </Badge>
                </div>
            ))}
        </div>
    );
}
