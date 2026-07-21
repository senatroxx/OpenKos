import { Head } from '@inertiajs/react';
import { useState } from 'react';
import SubmitPortalPaymentSheet from '@/components/features/payments/submit-portal-payment-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type { Invoice } from '@/types';

type PaymentLease = {
    invoices: Invoice[];
};

type PortalPayment = {
    id: number;
    amount: string;
    payment_date: string;
    payment_method: string;
    status: string;
    invoice: Invoice & { reference: string | null };
};

export default function Payments({
    leases,
    pendingPayments,
    paymentHistory,
}: {
    leases: PaymentLease[];
    pendingPayments: PortalPayment[];
    paymentHistory: PortalPayment[];
}) {
    const [invoiceToPay, setInvoiceToPay] = useState<Invoice | null>(null);
    const outstandingInvoices = leases.flatMap((item) => item.invoices);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <Head title="Billing" />

            <div>
                <p className="text-sm text-muted-foreground">Tenant Portal</p>
                <h1 className="text-2xl font-semibold">Billing</h1>
            </div>

            <section className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Outstanding Invoices</h2>
                        <p className="text-sm text-muted-foreground">
                            Pay an invoice and we will verify your submission.
                        </p>
                    </div>
                </div>

                {outstandingInvoices.length === 0 ? (
                    <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                        You have no outstanding invoices.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {outstandingInvoices.map((item) => (
                            <div
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {item.reference ??
                                            formatPeriod(item.period_start)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Due {formatDate(item.due_date)} ·{' '}
                                        <span className="tabular-nums">
                                            {formatPrice(
                                                item.outstanding ?? '0',
                                            )}
                                        </span>
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    onClick={() => setInvoiceToPay(item)}
                                >
                                    Pay Invoice
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="space-y-3">
                <div>
                    <h2 className="font-semibold">Pending Payments</h2>
                    <p className="text-sm text-muted-foreground">
                        Payments waiting for verification.
                    </p>
                </div>

                {pendingPayments.length === 0 ? (
                    <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                        No payments are waiting for verification.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {pendingPayments.map((payment) => (
                            <PaymentRow key={payment.id} payment={payment} />
                        ))}
                    </div>
                )}
            </section>

            <section className="space-y-3">
                <div>
                    <h2 className="font-semibold">Payment History</h2>
                    <p className="text-sm text-muted-foreground">
                        Your 10 most recent verified or cancelled payments.
                    </p>
                </div>

                {paymentHistory.length === 0 ? (
                    <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                        No payment history yet.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {paymentHistory.map((payment) => (
                            <PaymentRow key={payment.id} payment={payment} />
                        ))}
                    </div>
                )}
            </section>

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

function PaymentRow({ payment }: { payment: PortalPayment }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
            <div className="min-w-0">
                <p className="truncate font-medium">
                    {payment.invoice.reference ??
                        formatPeriod(payment.invoice.period_start)}
                </p>
                <p className="text-muted-foreground">
                    {formatDate(payment.payment_date)} ·{' '}
                    {payment.payment_method}
                </p>
            </div>
            <div className="flex items-center gap-3">
                <span className="font-medium tabular-nums">
                    {formatPrice(payment.amount)}
                </span>
                <StatusBadge domain="payment" value={payment.status} />
            </div>
        </div>
    );
}
