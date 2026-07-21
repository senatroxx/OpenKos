import { Badge } from '@/components/ui/badge';

type StatusConfig = {
    label: string;
    variant?: 'default' | 'secondary' | 'outline' | 'destructive';
    className?: string;
};

const STATUS_CONFIGS: Record<string, Record<string, StatusConfig>> = {
    unit: {
        available: { label: 'Available', className: 'bg-green-600 text-white' },
        occupied: { label: 'Occupied', className: 'bg-blue-600 text-white' },
        maintenance: {
            label: 'Maintenance',
            className: 'bg-amber-500 text-white',
        },
        unavailable: {
            label: 'Unavailable',
            className: 'bg-gray-400 text-white',
        },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    lease: {
        active: { label: 'Active', className: 'bg-green-600 text-white' },
        expired: { label: 'Expired', className: 'bg-gray-500 text-white' },
        terminated: {
            label: 'Terminated',
            className: 'bg-gray-400 text-white',
        },
        renewed: { label: 'Renewed', className: 'bg-blue-600 text-white' },
    },
    rent: {
        paid: { label: 'Paid', className: 'bg-green-600 text-white' },
        partial: { label: 'Partial', className: 'bg-blue-600 text-white' },
        overdue: { label: 'Overdue', className: 'bg-red-600 text-white' },
        due: { label: 'Due', className: 'bg-yellow-500 text-white' },
        due_today: { label: 'Due Today', className: 'bg-amber-600 text-white' },
        due_soon: { label: 'Due Soon', className: 'bg-blue-600 text-white' },
        upcoming: { label: 'Upcoming', className: 'bg-gray-400 text-white' },
        cancelled: {
            label: 'Cancelled',
            className: 'bg-gray-300 text-gray-700',
        },
    },
    payment: {
        confirmed: { label: 'Confirmed', className: 'bg-green-600 text-white' },
        pending: { label: 'Pending', className: 'bg-amber-500 text-white' },
        cancelled: { label: 'Cancelled', className: 'bg-gray-400 text-white' },
        verified: { label: 'Verified', className: 'bg-green-600 text-white' },
    },
    maintenance: {
        reported: { label: 'Reported', className: 'bg-blue-100 text-blue-700' },
        in_progress: {
            label: 'In Progress',
            className: 'bg-purple-100 text-purple-700',
        },
        resolved: {
            label: 'Resolved',
            className: 'bg-green-100 text-green-700',
        },
        cancelled: {
            label: 'Cancelled',
            className: 'bg-gray-100 text-gray-500',
        },
    },
    user: {
        active: { label: 'Active', className: 'bg-green-600 text-white' },
        invited: { label: 'Invited', variant: 'outline' },
        notified: { label: 'Email only', variant: 'outline' },
        disabled: { label: 'Disabled', variant: 'secondary' },
    },
    app_access: {
        active: { label: 'App access', className: 'bg-green-600 text-white' },
        invited: { label: 'Invite pending', className: 'bg-amber-500 text-white' },
        email_only: { label: 'Email only', variant: 'outline' },
        disabled: { label: 'Access disabled', variant: 'secondary' },
        none: { label: 'No access', variant: 'secondary' },
    },
    tenant: {
        active: { label: 'Active', className: 'bg-green-600 text-white' },
        inactive: { label: 'Inactive', className: 'bg-gray-400 text-white' },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    property: {
        active: { label: 'Active', className: 'bg-green-600 text-white' },
        archived: { label: 'Archived', variant: 'secondary' },
    },
    role: {
        active: { label: 'Active', className: 'bg-green-600 text-white' },
        disabled: { label: 'Disabled', variant: 'secondary' },
        system: { label: 'System', variant: 'secondary' },
        custom: { label: 'Custom', variant: 'outline' },
    },
    invoice: {
        pending: { label: 'Pending', className: 'bg-yellow-500 text-white' },
        partial: { label: 'Partial', className: 'bg-blue-600 text-white' },
        overdue: { label: 'Overdue', className: 'bg-red-600 text-white' },
        paid: { label: 'Paid', className: 'bg-green-600 text-white' },
        cancelled: { label: 'Cancelled', className: 'bg-gray-400 text-white' },
        void: { label: 'Void', className: 'bg-gray-300 text-gray-700' },
    },
};

export function StatusBadge({
    domain,
    value,
}: {
    domain: keyof typeof STATUS_CONFIGS;
    value: string;
}) {
    const config = STATUS_CONFIGS[domain]?.[value];

    if (!config) {
        return <Badge variant="outline">{value}</Badge>;
    }

    if (config.variant) {
        return (
            <Badge variant={config.variant} className={config.className}>
                {config.label}
            </Badge>
        );
    }

    return <Badge className={config.className}>{config.label}</Badge>;
}
