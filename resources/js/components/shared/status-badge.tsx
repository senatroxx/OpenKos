import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';

type StatusConfig = {
    label: string;
    variant?: 'default' | 'secondary' | 'outline' | 'destructive';
    className?: string;
};

const STATUS_CONFIGS: Record<string, Record<string, StatusConfig>> = {
    unit: {
        available: {
            label: 'Available',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        occupied: {
            label: 'Occupied',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        maintenance: {
            label: 'Maintenance',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        unavailable: {
            label: 'Unavailable',
            variant: 'secondary',
        },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    lease: {
        active: {
            label: 'Active',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        expired: { label: 'Expired', variant: 'secondary' },
        terminated: {
            label: 'Terminated',
            variant: 'secondary',
        },
        renewed: {
            label: 'Renewed',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
    },
    rent: {
        paid: {
            label: 'Paid',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        partial: {
            label: 'Partial',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        overdue: {
            label: 'Overdue',
            className:
                'bg-surface-red/70 text-surface-red-foreground border-surface-red-border/80',
        },
        due: {
            label: 'Due',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        due_today: {
            label: 'Due Today',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        due_soon: {
            label: 'Due Soon',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        upcoming: { label: 'Upcoming', variant: 'outline' },
        cancelled: {
            label: 'Cancelled',
            variant: 'secondary',
        },
    },
    payment: {
        confirmed: {
            label: 'Confirmed',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        pending: {
            label: 'Pending',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        cancelled: { label: 'Rejected', variant: 'secondary' },
        verified: {
            label: 'Verified',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
    },
    tenant_payment: {
        confirmed: {
            label: 'Verified',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        pending: {
            label: 'Awaiting Verification',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        cancelled: { label: 'Rejected', variant: 'secondary' },
        verified: {
            label: 'Verified',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        expired: { label: 'Expired', variant: 'secondary' },
    },
    gateway_attempt: {
        pending: {
            label: 'Awaiting payment',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        settled: {
            label: 'Settled',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        failed: { label: 'Failed', variant: 'destructive' },
        expired: { label: 'Expired', variant: 'secondary' },
        canceled: { label: 'Canceled', variant: 'secondary' },
    },
    maintenance: {
        reported: {
            label: 'Reported',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        in_progress: {
            label: 'In Progress',
            className:
                'bg-surface-purple/70 text-surface-purple-foreground border-surface-purple-border/80',
        },
        resolved: {
            label: 'Resolved',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        cancelled: {
            label: 'Cancelled',
            variant: 'secondary',
        },
    },
    user: {
        active: {
            label: 'Active',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        invited: { label: 'Invited', variant: 'outline' },
        notified: { label: 'Email only', variant: 'outline' },
        disabled: { label: 'Disabled', variant: 'secondary' },
    },
    app_access: {
        active: {
            label: 'App access',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        invited: {
            label: 'Invite pending',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        email_only: { label: 'Email only', variant: 'outline' },
        disabled: { label: 'Access disabled', variant: 'secondary' },
        none: { label: 'No access', variant: 'secondary' },
    },
    tenant: {
        active: {
            label: 'Active',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        inactive: { label: 'Inactive', variant: 'secondary' },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    property: {
        active: {
            label: 'Active',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    role: {
        active: {
            label: 'Active',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        disabled: { label: 'Disabled', variant: 'secondary' },
        system: { label: 'System', variant: 'secondary' },
        custom: { label: 'Custom', variant: 'outline' },
    },
    invoice: {
        pending: {
            label: 'Pending',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        partial: {
            label: 'Partial',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        overdue: {
            label: 'Overdue',
            className:
                'bg-surface-red/70 text-surface-red-foreground border-surface-red-border/80',
        },
        paid: {
            label: 'Paid',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        cancelled: { label: 'Cancelled', variant: 'secondary' },
        void: { label: 'Void', variant: 'secondary' },
    },
    tenant_invoice: {
        pending: {
            label: 'Pending',
            className:
                'bg-surface-amber/70 text-surface-amber-foreground border-surface-amber-border/80',
        },
        partial: {
            label: 'Partially Paid',
            className:
                'bg-surface-blue/70 text-surface-blue-foreground border-surface-blue-border/80',
        },
        overdue: {
            label: 'Overdue',
            className:
                'bg-surface-red/70 text-surface-red-foreground border-surface-red-border/80',
        },
        paid: {
            label: 'Paid',
            className:
                'bg-surface-green/70 text-surface-green-foreground border-surface-green-border/80',
        },
        cancelled: { label: 'Cancelled', variant: 'secondary' },
        void: { label: 'Void', variant: 'secondary' },
    },
};

export function StatusBadge({
    domain = 'lease',
    value,
    status,
}: {
    domain?: keyof typeof STATUS_CONFIGS;
    value?: string;
    status?: string;
}) {
    const val = value ?? status ?? '';
    const config = STATUS_CONFIGS[domain]?.[val];

    if (!config) {
        return <Badge variant="outline">{t(val)}</Badge>;
    }

    if (config.variant) {
        return (
            <Badge variant={config.variant} className={config.className}>
                {t(config.label)}
            </Badge>
        );
    }

    return <Badge className={config.className}>{t(config.label)}</Badge>;
}
