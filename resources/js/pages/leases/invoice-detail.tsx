import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PaymentDetailSheet } from '@/components/features';
import { DocumentPreview } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import paymentRoutes from '@/routes/payments';
import type { Invoice, Payment, PaymentProof, WorkspaceLease } from '@/types';

export default function InvoiceDetail({
    lease,
    invoice,
}: {
    lease: WorkspaceLease;
    invoice: Invoice;
}) {
    const { auth } = usePage<{ auth: { permissions: string[] } }>().props;
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [selectedPaymentId, setSelectedPaymentId] = useState<number | null>(
        null,
    );
    const [detailOpen, setDetailOpen] = useState(false);
    const [previewProof, setPreviewProof] = useState<{
        src: string;
        mimeType: string;
        name: string;
    } | null>(null);
    const canVerify = auth.permissions.includes('payments.verify');
    const selectedPayment = (
        invoice.payments?.find((payment) => payment.id === selectedPaymentId)
            ? {
                  ...invoice.payments.find(
                      (payment) => payment.id === selectedPaymentId,
                  )!,
                  invoice: {
                      id: invoice.id,
                      reference: invoice.reference,
                      period_start: invoice.period_start,
                      period_end: invoice.period_end,
                      status: invoice.status,
                  },
              }
            : null
    ) as Payment | null;

    useEffect(() => {
        if (detailOpen && selectedPaymentId !== null && selectedPayment === null) {
            setDetailOpen(false);
            setSelectedPaymentId(null);
        }
    }, [detailOpen, selectedPayment, selectedPaymentId]);

    function handlePreview(payment: Payment, proof: PaymentProof) {
        setPreviewProof({
            src: paymentRoutes.proof.url([payment, proof]),
            mimeType: proof.mime_type,
            name: proof.original_name,
        });
    }

    function handleVerify(payment: Payment, action: 'confirm' | 'reject') {
        setVerifyingId(payment.id);
        router.post(
            paymentRoutes.verify.url(payment),
            { action },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setVerifyingId(null),
            },
        );
    }

    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Head title={`Invoice ${invoice.reference} — Lease #${lease.id}`} />

            <Link
                href={`/leases/${lease.id}/invoices`}
                className="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to invoices
            </Link>

            <div className="flex-1 space-y-6">
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
                        <StatusBadge
                            domain="invoice"
                            value={invoice.display_status ?? invoice.status}
                        />
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
                            <p className="text-muted-foreground">
                                Billing period
                            </p>
                            <p className="mt-1 tabular-nums">
                                {formatDate(invoice.period_start)} —{' '}
                                {formatDate(invoice.period_end)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Due date</p>
                            <p className="mt-1 tabular-nums">
                                {formatDate(invoice.due_date)}
                            </p>
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
                                        <p className="font-medium">
                                            {item.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.type}
                                        </p>
                                    </div>
                                    <p className="tabular-nums">
                                        {formatPrice(item.amount)}
                                    </p>
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
                                    className="flex cursor-pointer items-center justify-between px-4 py-3 text-sm hover:bg-muted/30"
                                    onClick={() => {
                                        setSelectedPaymentId(payment.id);
                                        setDetailOpen(true);
                                    }}
                                >
                                    <div>
                                        <p className="font-medium tabular-nums">
                                            {formatPrice(payment.amount)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(payment.payment_date)}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        domain="payment"
                                        value={payment.status}
                                    />
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

            <PaymentDetailSheet
                payment={selectedPayment}
                open={detailOpen}
                onOpenChange={(open) => {
                    setDetailOpen(open);
                    if (!open) {
                        setSelectedPaymentId(null);
                    }
                }}
                verifyingId={verifyingId}
                canVerify={canVerify}
                onPreview={handlePreview}
                onVerify={handleVerify}
            />

            {previewProof && (
                <DocumentPreview
                    src={previewProof.src}
                    mimeType={previewProof.mimeType}
                    title={previewProof.name}
                    subtitle="Payment Proof"
                    onClose={() => setPreviewProof(null)}
                />
            )}
        </div>
    );
}
