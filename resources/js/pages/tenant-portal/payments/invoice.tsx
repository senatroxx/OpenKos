import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import SubmitPortalPaymentSheet from '@/components/features/payments/submit-portal-payment-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { index } from '@/routes/portal/billing';
import type { Invoice } from '@/types';

type InvoiceLease = {
    reference: string | null;
    unit_name: string | null;
    property_name: string | null;
};

export default function InvoiceDetail({
    invoice,
    lease,
}: {
    invoice: Invoice;
    lease: InvoiceLease;
}) {
    const [paymentOpen, setPaymentOpen] = useState(false);
    const isPayable = ['pending', 'partial'].includes(invoice.status);

    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Head title={`Invoice ${invoice.reference ?? ''}`} />

            <Link
                href={index()}
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to billing
            </Link>

            <div className="space-y-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        {invoice.reference ?? 'Invoice'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {formatPeriod(invoice.period_start, 'id-ID')}
                    </p>
                    <p className="mt-3 text-sm text-muted-foreground">
                        {lease.reference ?? 'Lease'}
                        {lease.unit_name && ` · ${lease.unit_name}`}
                        {lease.property_name && ` · ${lease.property_name}`}
                    </p>
                </header>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div className="space-y-6">
                        <section className="rounded-lg border">
                            <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                What this invoice covers
                            </h2>
                            {invoice.line_items &&
                            invoice.line_items.length > 0 ? (
                                <div className="divide-y">
                                    {invoice.line_items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                                        >
                                            <span>{item.description}</span>
                                            <span className="tabular-nums">
                                                {formatPrice(item.amount)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="px-4 py-6 text-sm text-muted-foreground">
                                    No itemized charges.
                                </p>
                            )}
                        </section>

                        {invoice.payments && invoice.payments.length > 0 && (
                            <section className="rounded-lg border">
                                <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Payment activity
                                </h2>
                                <div className="divide-y">
                                    {invoice.payments.map((payment) => (
                                        <div
                                            key={payment.id}
                                            className="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium tabular-nums">
                                                    {formatPrice(
                                                        payment.amount,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                domain="tenant_payment"
                                                value={payment.status}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>

                    <aside className="order-first rounded-lg border p-5 lg:sticky lg:top-16 lg:order-none">
                        <p className="text-sm text-muted-foreground">
                            Outstanding balance
                        </p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {formatPrice(invoice.outstanding ?? '0')}
                        </p>
                        <div className="mt-5 grid gap-4 text-sm">
                            <Detail
                                label="Due date"
                                value={formatDate(invoice.due_date)}
                            />
                            <div>
                                <p className="text-muted-foreground">Status</p>
                                <div className="mt-1">
                                    <StatusBadge
                                        domain="tenant_invoice"
                                        value={
                                            invoice.display_status ??
                                            invoice.status
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                        {isPayable && (
                            <Button
                                className="mt-6 w-full"
                                onClick={() => setPaymentOpen(true)}
                            >
                                Pay invoice
                            </Button>
                        )}
                    </aside>
                </div>
            </div>

            <SubmitPortalPaymentSheet
                invoice={invoice}
                open={paymentOpen}
                onOpenChange={setPaymentOpen}
            />
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium tabular-nums">{value}</p>
        </div>
    );
}
