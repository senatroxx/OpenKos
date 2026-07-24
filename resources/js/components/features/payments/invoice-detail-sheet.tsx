import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Banknote } from 'lucide-react';
import { useState } from 'react';
import { DocumentPreview } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import leases from '@/routes/leases';
import leaseInvoices from '@/routes/leases/workspace/invoices';
import paymentRoutes from '@/routes/payments';
import type {
    NeedsAttentionInvoice,
    Payment,
    PaymentProof,
} from '@/types';
import PaymentDetailSheet from './payment-detail-sheet';

export default function InvoiceDetailSheet({
    invoice,
    open,
    onOpenChange,
    onRecordPayment,
}: {
    invoice: NeedsAttentionInvoice | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onRecordPayment: (invoice: NeedsAttentionInvoice) => void;
}) {
    const { auth } = usePage<{ auth: { permissions: string[] } }>().props;
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [selectedPaymentId, setSelectedPaymentId] = useState<number | null>(
        null,
    );
    const [previewProof, setPreviewProof] = useState<{
        src: string;
        mimeType: string;
        name: string;
    } | null>(null);
    const canVerify = auth.permissions.includes('payments.verify');
    const selectedPaymentData = invoice?.payments?.find((payment) => payment.id === selectedPaymentId);
    const selectedPayment = (selectedPaymentData && invoice
        ? {
              ...selectedPaymentData,
              invoice: {
                  id: invoice.id,
                  reference: invoice.reference,
                  period_start: invoice.period_start,
                  period_end: invoice.period_end,
                  status: invoice.status,
              },
          }
        : null) as Payment | null;
    const paymentDetailOpen =
        selectedPaymentId !== null && selectedPayment !== null;

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
        <>
            <Sheet
                open={open && !paymentDetailOpen}
                onOpenChange={onOpenChange}
            >
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {invoice?.reference ?? 'Invoice'}
                        </SheetTitle>
                    </SheetHeader>

                    {invoice && (
                        <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                            <div className="space-y-6">
                                <section className="rounded-lg border p-4">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="font-medium">
                                                {formatPeriod(
                                                    invoice.period_start,
                                                    'id-ID',
                                                )}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {invoice.tenant_name}
                                                {' · '}
                                                {invoice.unit_name}
                                                {' · '}
                                                {invoice.property_name}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            domain="invoice"
                                            value={invoice.status}
                                        />
                                    </div>

                                    <div className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                        <DetailRow
                                            label="Total"
                                            value={formatPrice(invoice.total)}
                                        />
                                        <DetailRow
                                            label="Paid"
                                            value={formatPrice(
                                                invoice.amount_paid,
                                            )}
                                        />
                                        <DetailRow
                                            label="Outstanding"
                                            value={formatPrice(
                                                invoice.outstanding,
                                            )}
                                        />
                                        <DetailRow
                                            label="Due date"
                                            value={formatDate(
                                                invoice.due_date,
                                            )}
                                        />
                                        <DetailRow
                                            label="Lease"
                                            value={
                                                invoice.lease_reference ??
                                                `#${invoice.lease_id}`
                                            }
                                        />
                                    </div>
                                </section>

                                {invoice.line_items &&
                                    invoice.line_items.length > 0 && (
                                        <section className="rounded-lg border">
                                            <div className="border-b px-4 py-3">
                                                <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                    Line Items
                                                </h3>
                                            </div>
                                            <div className="divide-y">
                                                {invoice.line_items.map(
                                                    (item) => (
                                                        <div
                                                            key={item.id}
                                                            className="flex items-center justify-between px-4 py-3 text-sm"
                                                        >
                                                            <div>
                                                                <p className="font-medium">
                                                                    {
                                                                        item.description
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {item.type}
                                                                </p>
                                                            </div>
                                                            <p className="tabular-nums">
                                                                {formatPrice(
                                                                    item.amount,
                                                                )}
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </section>
                                    )}

                                <section className="rounded-lg border">
                                    <div className="border-b px-4 py-3">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Payments
                                        </h3>
                                    </div>
                                    {invoice.payments &&
                                    invoice.payments.length > 0 ? (
                                        <div className="divide-y">
                                            {invoice.payments.map((payment) => (
                                                <div
                                                    key={payment.id}
                                                    className="flex cursor-pointer items-center justify-between px-4 py-3 text-sm hover:bg-muted/30"
                                                    onClick={() => {
                                                        setSelectedPaymentId(
                                                            payment.id,
                                                        );
                                                    }}
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
                                                        domain="payment"
                                                        value={payment.status}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="px-4 py-6 text-sm text-muted-foreground">
                                            No payments recorded yet.
                                        </div>
                                    )}
                                </section>
                            </div>

                            <div className="flex flex-wrap items-center justify-end gap-4">
                                {invoice.status !== 'paid' && (
                                    <Button
                                        type="button"
                                        onClick={() => onRecordPayment(invoice)}
                                    >
                                        <Banknote className="size-4" />
                                        Record Payment
                                    </Button>
                                )}
                                <Button variant="outline" asChild>
                                    <Link
                                        href={leaseInvoices.show.url([
                                            { id: invoice.lease_id },
                                            { id: invoice.id },
                                        ])}
                                    >
                                        <ArrowUpRight className="size-4" />
                                        View Invoice
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={leases.show.url({
                                            id: invoice.lease_id,
                                        })}
                                    >
                                        <ArrowUpRight className="size-4" />
                                        View Lease
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Close
                                </Button>
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            <PaymentDetailSheet
                payment={selectedPayment}
                open={paymentDetailOpen}
                onOpenChange={(next) => {
                    if (!next) {
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
        </>
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium tabular-nums">{value}</p>
        </div>
    );
}
