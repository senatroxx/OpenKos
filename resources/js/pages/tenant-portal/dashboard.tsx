import { Head, Link } from '@inertiajs/react';
import { ChevronRight, CreditCard, House, ReceiptText } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/portal';
import { index as billingIndex } from '@/routes/portal/billing';
import { payments as paymentHistory } from '@/routes/portal/billing/history';
import { show as showInvoice } from '@/routes/portal/billing/invoices';
import { show as showLease } from '@/routes/portal/lease';
import type { MoneyAggregate } from '@/types';

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    currency: string;
    status: string;
    unit: {
        name: string;
        property: { name: string } | null;
    } | null;
};

type PendingPayment = {
    amount: string;
    currency: string;
    payment_date: string;
};

type NextAction =
    | { type: 'no_active_stay' }
    | { type: 'no_payment_required' }
    | { type: 'payment_verification'; pending_payment: PendingPayment }
    | {
          type: 'payment_required';
          invoice: {
              id: number;
              due_date: string;
              display_status: string;
              amount: string;
              currency: string;
          };
          pending_payment: PendingPayment | null;
      };

type AccountSummary = {
    outstanding_amounts: MoneyAggregate[];
    payable_invoice_count: number;
    pending_verification_count: number;
    next_due_date: string | null;
};

type Activity = {
    type:
        | 'payment_submitted'
        | 'payment_confirmed'
        | 'payment_cancelled'
        | 'invoice_issued'
        | 'lease_started';
    date: string;
    amount: string | null;
    currency: string;
    reference: string | null;
};

type Props = {
    lease: Lease | null;
    nextAction: NextAction;
    accountSummary: AccountSummary;
    recentActivity: Activity[];
};

export default function Dashboard({
    lease,
    nextAction,
    accountSummary,
    recentActivity,
}: Props) {
    return (
        <>
            <Head title={t('Tenant Portal')} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4">
                <div>
                    <h1 className="text-xl font-semibold text-balance">
                        {t('Dashboard')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('Overview of your stay and billing.')}
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-12">
                    <CurrentPaymentCard
                        nextAction={nextAction}
                        className="lg:col-span-7"
                    />
                    <AccountSummaryCard
                        summary={accountSummary}
                        className="lg:col-span-5"
                    />
                    {lease && (
                        <ActiveStayCard
                            lease={lease}
                            className="lg:order-4 lg:col-span-5"
                        />
                    )}
                    <RecentActivityCard
                        activity={recentActivity}
                        className="lg:order-3 lg:col-span-7"
                    />
                </div>
            </div>
        </>
    );
}

function CurrentPaymentCard({
    nextAction,
    className,
}: {
    nextAction: NextAction;
    className: string;
}) {
    if (nextAction.type === 'payment_required') {
        return (
            <Card className={`gap-4 py-5 ${className}`}>
                <CardHeader className="px-5 pb-0">
                    <CardTitle>{t('Payment required')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 px-5">
                    <StatusBadge
                        domain="tenant_invoice"
                        value={nextAction.invoice.display_status}
                    />
                    <div>
                        <p className="text-3xl font-semibold tracking-tight tabular-nums">
                            {formatPrice(
                                nextAction.invoice.amount,
                                nextAction.invoice.currency,
                            )}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('Due')} {formatDate(nextAction.invoice.due_date)}
                        </p>
                    </div>
                    {nextAction.pending_payment && (
                        <p className="text-sm text-muted-foreground">
                            {t('A payment of')}{' '}
                            {formatPrice(
                                nextAction.pending_payment.amount,
                                nextAction.pending_payment.currency,
                            )}{' '}
                            {t('submitted')}{' '}
                            {formatDate(
                                nextAction.pending_payment.payment_date,
                            )}{' '}
                            {t('is awaiting verification.')}
                        </p>
                    )}
                    <Button asChild size="sm">
                        <Link href={showInvoice(nextAction.invoice.id)}>
                            {t('View invoice')}
                            <ChevronRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (nextAction.type === 'payment_verification') {
        return (
            <Card className={`gap-4 py-5 ${className}`}>
                <CardHeader className="px-5 pb-0">
                    <CardTitle>{t('Payment awaiting verification')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 px-5">
                    <StatusBadge domain="tenant_payment" value="pending" />
                    <p className="text-sm text-muted-foreground">
                        {t('Your payment of')}{' '}
                        {formatPrice(
                            nextAction.pending_payment.amount,
                            nextAction.pending_payment.currency,
                        )}{' '}
                        {t('submitted')}{' '}
                        {formatDate(nextAction.pending_payment.payment_date)} is
                        {t(
                            'is being reviewed. You do not need to do anything right now.',
                        )}
                    </p>
                    <Button asChild size="sm" variant="outline">
                        <Link href={billingIndex()}>
                            {t('View billing')}
                            <ChevronRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={`gap-4 py-5 ${className}`}>
            <CardHeader className="px-5 pb-0">
                <CardTitle>
                    {nextAction.type === 'no_active_stay'
                        ? t('No active lease')
                        : t('All caught up')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 px-5">
                <Badge variant="secondary">
                    {nextAction.type === 'no_active_stay'
                        ? t('No active stay')
                        : t('No payment required')}
                </Badge>
                <p className="text-sm text-muted-foreground">
                    {nextAction.type === 'no_active_stay'
                        ? t(
                              'There is no active lease associated with your account.',
                          )
                        : t('You do not need to make a payment right now.')}
                </p>
                {nextAction.type === 'no_payment_required' && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={billingIndex()}>
                            {t('View billing')}
                            <ChevronRight />
                        </Link>
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function AccountSummaryCard({
    summary,
    className,
}: {
    summary: AccountSummary;
    className: string;
}) {
    return (
        <Card className={`gap-4 py-5 ${className}`}>
            <CardHeader className="flex-row items-center justify-between gap-3 px-5 pb-0">
                <CardTitle>{t('Account summary')}</CardTitle>
                <Button asChild size="sm" variant="ghost">
                    <Link href={billingIndex()}>
                        {t('View billing')}
                        <ChevronRight />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="space-y-3 px-5 text-sm">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {t('Outstanding balance')}
                    </p>
                    <p className="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                        {summary.outstanding_amounts
                            .map((amount) =>
                                formatPrice(amount.amount, amount.currency),
                            )
                            .join(' · ') || formatPrice('0')}
                    </p>
                </div>
                <div className="space-y-2 border-t pt-3">
                    <SummaryItem
                        label={t('Payable invoices')}
                        value={String(summary.payable_invoice_count)}
                    />
                    <SummaryItem
                        label={t('Awaiting verification')}
                        value={String(summary.pending_verification_count)}
                    />
                    {summary.next_due_date && (
                        <SummaryItem
                            label={t('Next due date')}
                            value={formatDate(summary.next_due_date)}
                        />
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ActiveStayCard({
    lease,
    className,
}: {
    lease: Lease;
    className: string;
}) {
    return (
        <Card className={`gap-4 py-5 ${className}`}>
            <CardHeader className="px-5 pb-0">
                <CardTitle>{t('Active stay')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 px-5">
                <div className="flex items-start justify-between gap-3">
                    <p className="font-medium">
                        {lease.unit?.name ?? '—'} ·{' '}
                        {lease.unit?.property?.name ?? '—'}
                    </p>
                    <StatusBadge domain="lease" value={lease.status} />
                </div>
                <p className="text-sm text-muted-foreground">
                    {formatDate(lease.start_date)} —{' '}
                    {lease.end_date ? formatDate(lease.end_date) : t('Ongoing')}
                </p>
                <Button asChild size="sm" variant="outline">
                    <Link href={showLease(lease.id)}>
                        {t('View lease')}
                        <ChevronRight />
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function RecentActivityCard({
    activity,
    className,
}: {
    activity: Activity[];
    className: string;
}) {
    return (
        <Card className={`gap-4 py-5 ${className}`}>
            <CardHeader className="flex-row items-center justify-between gap-3 px-5 pb-0">
                <CardTitle>{t('Recent activity')}</CardTitle>
                <Button asChild size="sm" variant="ghost">
                    <Link href={paymentHistory()}>
                        {t('View history')}
                        <ChevronRight />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="px-5">
                {activity.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No recent activity yet.')}
                    </p>
                ) : (
                    <div className="divide-y">
                        {activity.map((item, index) => (
                            <div
                                key={`${item.type}-${item.date}-${index}`}
                                className="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <ActivityIcon type={item.type} />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {activityLabel(item.type)}
                                        </p>
                                        {activitySupport(item) && (
                                            <p className="truncate text-sm text-muted-foreground">
                                                {activitySupport(item)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <span className="shrink-0 text-sm text-muted-foreground">
                                    {formatDate(item.date)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{value}</span>
        </div>
    );
}

function activityLabel(type: Activity['type']): string {
    return {
        payment_submitted: t('Payment submitted'),
        payment_confirmed: t('Payment confirmed'),
        payment_cancelled: t('Payment cancelled'),
        invoice_issued: t('Invoice issued'),
        lease_started: t('Lease started'),
    }[type];
}

function ActivityIcon({ type }: { type: Activity['type'] }) {
    const Icon =
        type === 'invoice_issued'
            ? ReceiptText
            : type === 'lease_started'
              ? House
              : CreditCard;

    return <Icon className="size-4" aria-hidden="true" />;
}

function activitySupport(item: Activity): string | null {
    const support = [
        item.amount && formatPrice(item.amount, item.currency),
        item.reference,
    ].filter(Boolean);

    return support.length > 0 ? support.join(' · ') : null;
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Tenant Portal', href: dashboard() }],
};
