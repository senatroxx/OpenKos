import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { invoices } from '@/routes/portal/lease';
import type { Invoice, Lease } from '@/types';

export default function InvoiceDetail({
    lease,
    invoice,
}: {
    lease: Lease;
    invoice: Invoice;
}) {
    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Head title={`Invoice ${invoice.reference ?? ''}`} />

            <Link
                href={invoices(lease)}
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to invoices
            </Link>

            <div className="space-y-6">
                <div className="rounded-lg border p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-lg font-semibold">
                                {invoice.reference ?? 'Invoice'}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {formatPeriod(invoice.period_start, 'id-ID')}
                            </p>
                        </div>
                        <StatusBadge
                            domain="invoice"
                            value={invoice.display_status ?? invoice.status}
                        />
                    </div>

                    <div className="mt-6 grid gap-4 text-sm sm:grid-cols-3">
                        <Detail label="Total" value={formatPrice(invoice.total)} />
                        <Detail label="Paid" value={formatPrice(invoice.amount_paid)} />
                        <Detail label="Outstanding" value={formatPrice(invoice.outstanding ?? '0')} />
                        <Detail label="Period" value={`${formatDate(invoice.period_start)} - ${formatDate(invoice.period_end)}`} />
                        <Detail label="Due date" value={formatDate(invoice.due_date)} />
                    </div>
                </div>

                {invoice.line_items && invoice.line_items.length > 0 && (
                    <section className="rounded-lg border">
                        <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Line Items
                        </h2>
                        <div className="divide-y">
                            {invoice.line_items.map((item) => (
                                <div key={item.id} className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                    <span>{item.description}</span>
                                    <span className="tabular-nums">{formatPrice(item.amount)}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {invoice.payments && invoice.payments.length > 0 && (
                    <section className="rounded-lg border">
                        <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Payments
                        </h2>
                        <div className="divide-y">
                            {invoice.payments.map((payment) => (
                                <div key={payment.id} className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                    <div>
                                        <p className="font-medium tabular-nums">{formatPrice(payment.amount)}</p>
                                        <p className="text-xs text-muted-foreground">{formatDate(payment.payment_date)}</p>
                                    </div>
                                    <StatusBadge domain="payment" value={payment.status} />
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
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
