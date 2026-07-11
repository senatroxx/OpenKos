import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type { Invoice, WorkspaceLease } from '@/types';
import { LeaseLayout } from './layout';

export default function InvoiceDetail({
    lease,
    invoice,
}: {
    lease: WorkspaceLease;
    invoice: Invoice;
}) {
    return (
        <LeaseLayout lease={lease} activeTab="invoices">
            <Head title={`Invoice ${invoice.reference} — Lease #${lease.id}`} />

            <Link
                href={`/leases/${lease.id}/invoices`}
                className="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ChevronLeft className="size-4" />
                Back to invoices
            </Link>

            <div className="space-y-6">

            {/* Summary card */}
            <div className="rounded-lg border p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {invoice.reference ?? 'Invoice'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {formatPeriod(invoice.period_start, 'id-ID')}
                        </p>
                    </div>
                    <StatusBadge domain="invoice" value={invoice.status} />
                </div>

                <div className="mt-6 grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p className="text-muted-foreground">Total</p>
                        <p className="mt-1 font-medium tabular-nums">
                            {formatPrice(invoice.total)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Paid</p>
                        <p className="mt-1 font-medium tabular-nums">
                            {formatPrice(invoice.amount_paid)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Outstanding</p>
                        <p className="mt-1 font-medium tabular-nums">
                            {formatPrice(invoice.outstanding ?? '0')}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Billing period</p>
                        <p className="mt-1 tabular-nums">
                            {formatDate(invoice.period_start)} — {formatDate(invoice.period_end)}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Due date</p>
                        <p className="mt-1 tabular-nums">{formatDate(invoice.due_date)}</p>
                    </div>
                </div>
            </div>

            {/* Line items */}
            {invoice.line_items && invoice.line_items.length > 0 && (
                <div className="rounded-lg border">
                    <div className="border-b px-4 py-3">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Line Items
                        </h3>
                    </div>
                    <div className="divide-y">
                        {invoice.line_items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between px-4 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium">{item.description}</p>
                                    <p className="text-xs text-muted-foreground">{item.type}</p>
                                </div>
                                <p className="tabular-nums">{formatPrice(item.amount)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Allocated payments */}
            {invoice.payments && invoice.payments.length > 0 && (
                <div className="rounded-lg border">
                    <div className="border-b px-4 py-3">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Payments
                        </h3>
                    </div>
                    <div className="divide-y">
                        {invoice.payments.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex items-center justify-between px-4 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium tabular-nums">
                                        {formatPrice(payment.amount)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDate(payment.payment_date)}
                                    </p>
                                </div>
                                <StatusBadge domain="payment" value={payment.status} />
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Timeline placeholder */}
            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                Activity timeline, reminders, and PDF download coming soon.
            </div>
            </div>
        </LeaseLayout>
    );
}
