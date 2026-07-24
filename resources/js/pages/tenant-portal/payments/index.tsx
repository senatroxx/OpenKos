import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InvoiceActionItem from '@/components/features/payments/invoice-action-item';
import SubmitPortalPaymentSheet from '@/components/features/payments/submit-portal-payment-sheet';
import TenantLeaseContext from '@/components/features/tenant-portal/lease-context';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { index as billingIndex } from '@/routes/portal/billing';
import {
    invoices as invoiceHistory,
    payments as paymentHistory,
} from '@/routes/portal/billing/history';
import { show as showInvoice } from '@/routes/portal/billing/invoices';
import type {
    Invoice,
    PaginatedData,
    TenantLeaseContext as LeaseContext,
} from '@/types';

type PortalPayment = {
    id: number;
    amount: string;
    payment_date: string;
    payment_method: string;
    status: string;
    invoice: Invoice & { reference: string | null };
};

export default function Payments({
    actionableInvoices,
    historicalInvoices,
    historicalInvoiceCount,
    pendingPayments,
    finalizedPayments,
    finalizedPaymentCount,
    outstandingSummary,
    leaseContext,
}: {
    actionableInvoices: PaginatedData<Invoice>;
    historicalInvoices: Invoice[];
    historicalInvoiceCount: number;
    pendingPayments: PortalPayment[];
    finalizedPayments: PortalPayment[];
    finalizedPaymentCount: number;
    outstandingSummary: {
        amount: string;
        count: number;
        next_due_date: string | null;
        pending_payment_count: number;
    };
    leaseContext: LeaseContext;
}) {
    const [invoiceToPay, setInvoiceToPay] = useState<Invoice | null>(null);
    const [visibleInvoices, setVisibleInvoices] = useState(
        actionableInvoices.data,
    );
    const [loadingMore, setLoadingMore] = useState(false);
    const [nextActionPage, setNextActionPage] = useState(
        actionableInvoices.current_page + 1,
    );
    const [hasMoreInvoices, setHasMoreInvoices] = useState(
        actionableInvoices.current_page < actionableInvoices.last_page,
    );

    function loadMore() {
        setLoadingMore(true);

        router.get(
            billingIndex({
                query: {
                    action_page: nextActionPage,
                    lease: leaseContext.selected?.id,
                },
            }).url,
            {},
            {
                only: ['actionableInvoices'],
                preserveScroll: true,
                preserveState: true,
                preserveUrl: true,
                replace: true,
                onSuccess: (page) => {
                    const nextPage = page.props
                        .actionableInvoices as PaginatedData<Invoice>;

                    setVisibleInvoices((invoices = []) => {
                        const invoiceIds = new Set(
                            invoices.map((invoice) => invoice.id),
                        );

                        return [
                            ...invoices,
                            ...nextPage.data.filter(
                                (invoice) => !invoiceIds.has(invoice.id),
                            ),
                        ];
                    });
                    setNextActionPage(nextPage.current_page + 1);
                    setHasMoreInvoices(
                        nextPage.current_page < nextPage.last_page,
                    );
                },
                onFinish: () => setLoadingMore(false),
            },
        );
    }

    return (
        <div className="flex w-full flex-1 flex-col gap-6 p-4">
            <Head title="Billing" />

            <div>
                <h1 className="text-2xl font-semibold">Billing</h1>
            </div>

            <TenantLeaseContext
                leaseContext={leaseContext}
                hrefForLease={(leaseId) =>
                    billingIndex({ query: { lease: leaseId } }).url
                }
            />

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                <aside className="order-1 space-y-3 lg:sticky lg:top-20 lg:order-2 lg:self-start">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Account summary
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            A quick view of what you owe and what is being
                            verified.
                        </p>
                    </div>
                    <AccountSummary
                        outstandingSummary={outstandingSummary}
                        lease={leaseContext.selected}
                    />
                </aside>

                <div className="order-2 flex flex-col gap-6 lg:order-1 lg:col-span-2">
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Action required
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Pay an invoice and we will verify your
                                submission.
                            </p>
                        </div>

                        {outstandingSummary.count === 0 ? (
                            <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                                You’re all caught up. There are no invoices
                                requiring payment.
                            </p>
                        ) : (
                            <>
                                <div className="divide-y rounded-lg border">
                                    {visibleInvoices.map((item) => (
                                        <InvoiceActionItem
                                            key={item.id}
                                            invoice={item}
                                            onPay={() => setInvoiceToPay(item)}
                                        />
                                    ))}
                                </div>

                                {hasMoreInvoices && (
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        disabled={loadingMore}
                                        onClick={loadMore}
                                    >
                                        {loadingMore
                                            ? 'Loading...'
                                            : 'Load more'}
                                    </Button>
                                )}
                            </>
                        )}
                    </section>

                    {pendingPayments.length > 0 && (
                        <section className="space-y-3">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    In progress
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Submitted payments are reviewed before they
                                    are applied to your invoice.
                                </p>
                            </div>
                            <div className="divide-y rounded-lg border">
                                {pendingPayments.map((payment) => (
                                    <PaymentRow
                                        key={payment.id}
                                        payment={payment}
                                        showDetails
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="space-y-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Your records
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Completed invoices and finalized payments.
                            </p>
                        </div>

                        <details className="group rounded-lg border">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-4 font-medium [&::-webkit-details-marker]:hidden">
                                Invoice history ({historicalInvoiceCount})
                                <ChevronDown className="size-4 text-muted-foreground transition-transform group-open:rotate-180" />
                            </summary>
                            <div className="border-t">
                                {historicalInvoices.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        No completed invoices yet.
                                    </p>
                                ) : (
                                    <div className="divide-y">
                                        {historicalInvoices.map((invoice) => (
                                            <InvoiceActionItem
                                                key={invoice.id}
                                                invoice={invoice}
                                                amount={invoice.total}
                                            />
                                        ))}
                                    </div>
                                )}
                                <div className="border-t p-3">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={invoiceHistory({
                                                query: {
                                                    lease: leaseContext.selected
                                                        ?.id,
                                                },
                                            })}
                                        >
                                            View all invoices
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </details>

                        <details className="group rounded-lg border">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-4 font-medium [&::-webkit-details-marker]:hidden">
                                Payment history ({finalizedPaymentCount})
                                <ChevronDown className="size-4 text-muted-foreground transition-transform group-open:rotate-180" />
                            </summary>
                            <div className="border-t">
                                {finalizedPayments.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        No finalized payments yet.
                                    </p>
                                ) : (
                                    <div className="divide-y">
                                        {finalizedPayments.map((payment) => (
                                            <PaymentRow
                                                key={payment.id}
                                                payment={payment}
                                            />
                                        ))}
                                    </div>
                                )}
                                <div className="border-t p-3">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={paymentHistory({
                                                query: {
                                                    lease: leaseContext.selected
                                                        ?.id,
                                                },
                                            })}
                                        >
                                            View all payments
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </details>
                    </section>
                </div>
            </div>

            {invoiceToPay && (
                <SubmitPortalPaymentSheet
                    invoice={invoiceToPay}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setInvoiceToPay(null);
                        }
                    }}
                />
            )}
        </div>
    );
}

function AccountSummary({
    outstandingSummary,
    lease,
}: {
    outstandingSummary: {
        amount: string;
        count: number;
        next_due_date: string | null;
        pending_payment_count: number;
    };
    lease: LeaseContext['selected'];
}) {
    const currentLeaseName = [lease?.unit_name, lease?.property_name]
        .filter(Boolean)
        .join(' · ');

    return (
        <Card className="gap-4 py-5">
            <CardContent className="grid gap-4 px-5">
                <SummaryItem label="Outstanding balance">
                    <span className="font-semibold tabular-nums">
                        {formatPrice(outstandingSummary.amount)}
                    </span>
                </SummaryItem>
                <SummaryItem label="Payable invoices">
                    {outstandingSummary.count}
                </SummaryItem>
                {currentLeaseName && (
                    <SummaryItem label="Current lease">
                        <span className="text-right">{currentLeaseName}</span>
                    </SummaryItem>
                )}
                {outstandingSummary.next_due_date && (
                    <SummaryItem label="Next due date">
                        {formatDate(outstandingSummary.next_due_date)}
                    </SummaryItem>
                )}
                {outstandingSummary.pending_payment_count > 0 && (
                    <SummaryItem label="Pending verification">
                        {outstandingSummary.pending_payment_count}
                    </SummaryItem>
                )}
            </CardContent>
        </Card>
    );
}

function SummaryItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{children}</span>
        </div>
    );
}

function PaymentRow({
    payment,
    showDetails = false,
}: {
    payment: PortalPayment;
    showDetails?: boolean;
}) {
    const paymentMethodLabel =
        PAYMENT_METHOD_LABELS[payment.payment_method] ?? payment.payment_method;

    return (
        <BillingQueueItem
            title={`${paymentMethodLabel} payment`}
            statusDomain="tenant_payment"
            status={payment.status}
            amount={formatPrice(payment.amount)}
            description={
                <>
                    Paid on {formatDate(payment.payment_date)} for{' '}
                    {formatPeriod(payment.invoice.period_start)} rent
                    {payment.invoice.reference && (
                        <span className="hidden sm:inline">
                            {' · '}
                            Invoice {payment.invoice.reference}
                        </span>
                    )}
                </>
            }
            mobileDescription={
                payment.invoice.reference
                    ? `${formatPeriod(payment.invoice.period_start)} rent · Invoice ${payment.invoice.reference}`
                    : `${formatPeriod(payment.invoice.period_start)} rent`
            }
            actions={
                showDetails ? (
                    <Button
                        variant="link"
                        className="h-10 w-fit px-0 sm:h-8 sm:px-2"
                        asChild
                    >
                        <Link href={showInvoice(payment.invoice)}>
                            View invoice <ChevronRight className="sm:hidden" />
                        </Link>
                    </Button>
                ) : undefined
            }
        />
    );
}

function BillingQueueItem({
    title,
    statusDomain,
    status,
    amount,
    description,
    mobileDescription,
    actions,
}: {
    title: string;
    statusDomain: 'tenant_invoice' | 'tenant_payment';
    status: string;
    amount: string;
    description: ReactNode;
    mobileDescription?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <article className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="min-w-0 truncate font-medium">{title}</p>
                    <StatusBadge domain={statusDomain} value={status} />
                </div>
                <p className="text-sm text-muted-foreground">{description}</p>
                {mobileDescription && (
                    <p className="text-xs text-muted-foreground sm:hidden">
                        {mobileDescription}
                    </p>
                )}
            </div>

            <span className="shrink-0 font-medium tabular-nums">{amount}</span>

            {actions && (
                <div className="grid w-full gap-1 sm:flex sm:w-auto sm:items-center sm:gap-2">
                    {actions}
                </div>
            )}
        </article>
    );
}
