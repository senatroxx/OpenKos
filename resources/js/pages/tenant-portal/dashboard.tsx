import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatPrice } from '@/lib/formatters';

type Invoice = {
    id: number;
    lease_id: number;
    reference: string | null;
    period_start: string;
    period_end: string;
    due_date: string;
    status: string;
    total: string;
    amount_paid: string;
    outstanding: string;
};

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_label: string;
    status: string;
    unit: {
        id: number;
        name: string;
        status: string;
        property: {
            id: number;
            name: string;
            address: string | null;
        } | null;
    } | null;
};

type Notification = {
    id: number;
    type: string;
    channel: string;
    scheduled_for: string | null;
    sent_at: string | null;
    overdue_days: number | null;
};

type Props = {
    tenant: { id: number; name: string };
    lease: Lease | null;
    rent: {
        status: string;
        upcoming_invoices: Invoice[];
    };
    notifications: Notification[];
};

export default function Dashboard({
    tenant,
    lease,
    rent,
    notifications,
}: Props) {
    return (
        <>
            <Head title="Tenant Portal" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Tenant Portal
                        </p>
                        <h1 className="text-2xl font-semibold">
                            Hi, {tenant.name}
                        </h1>
                    </div>

                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Lease</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {lease ? (
                                <>
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            Reference
                                        </span>
                                        <span>{lease.reference ?? '—'}</span>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            Period
                                        </span>
                                        <span>
                                            {formatDate(lease.start_date)} –{' '}
                                            {lease.end_date
                                                ? formatDate(lease.end_date)
                                                : 'ongoing'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            Rent
                                        </span>
                                        <span>
                                            {lease.rent_amount
                                                ? formatPrice(lease.rent_amount)
                                                : '—'}{' '}
                                            {lease.billing_label}
                                        </span>
                                    </div>
                                    <Badge variant="outline">
                                        {lease.status}
                                    </Badge>
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    No active lease found.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Room</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {lease?.unit ? (
                                <>
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            Unit
                                        </span>
                                        <span>{lease.unit.name}</span>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            Property
                                        </span>
                                        <span>
                                            {lease.unit.property?.name ?? '—'}
                                        </span>
                                    </div>
                                    {lease.unit.property?.address && (
                                        <p className="text-muted-foreground">
                                            {lease.unit.property.address}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    No room assigned.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rent Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Badge>{rentStatusLabel(rent.status)}</Badge>
                            {rent.upcoming_invoices[0] && (
                                <p className="text-sm text-muted-foreground">
                                    Next due{' '}
                                    {formatDate(
                                        rent.upcoming_invoices[0].due_date,
                                    )}
                                    :{' '}
                                    {formatPrice(
                                        rent.upcoming_invoices[0].outstanding,
                                    )}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Upcoming Payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {rent.upcoming_invoices.length > 0 ? (
                                <div className="space-y-3">
                                    {rent.upcoming_invoices.map((invoice) => (
                                        <div
                                            key={invoice.id}
                                            className="flex items-center justify-between gap-4 rounded-lg border p-3 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {invoice.reference ?? '—'}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    Due{' '}
                                                    {formatDate(
                                                        invoice.due_date,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">
                                                    {formatPrice(
                                                        invoice.outstanding,
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {invoice.status}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No payable invoices.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Notifications</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {notifications.length > 0 ? (
                                <div className="space-y-3">
                                    {notifications.map((notification) => (
                                        <div
                                            key={notification.id}
                                            className="rounded-lg border p-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {notificationLabel(
                                                    notification,
                                                )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {notification.channel}
                                                {notification.sent_at
                                                    ? ` · ${formatDate(notification.sent_at)}`
                                                    : ''}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No notifications yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function rentStatusLabel(status: string): string {
    if (status === 'none') {
        return 'No active lease';
    }

    return status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function notificationLabel(notification: Notification): string {
    const label = rentStatusLabel(notification.type);

    return notification.overdue_days
        ? `${label} · ${notification.overdue_days} days overdue`
        : label;
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Tenant Portal', href: '/portal/dashboard' }],
};
