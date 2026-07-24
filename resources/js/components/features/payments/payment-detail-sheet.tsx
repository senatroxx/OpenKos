import { Check, FileText, X } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import type { Payment, PaymentProof } from '@/types';

export default function PaymentDetailSheet({
    payment,
    open,
    onOpenChange,
    verifyingId,
    canVerify,
    onPreview,
    onVerify,
}: {
    payment: Payment | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    verifyingId: number | null;
    canVerify: boolean;
    onPreview: (payment: Payment, proof: PaymentProof) => void;
    onVerify: (payment: Payment, action: 'confirm' | 'reject') => void;
}) {
    const paymentUsers = payment as (Payment & {
        confirmed_by?: { id: number; name: string } | null;
        confirmedBy?: { id: number; name: string } | null;
    }) | null;
    const verifiedDate =
        payment?.status === 'confirmed'
            ? (payment.verified_at ?? payment.payment_date)
            : payment?.verified_at ?? null;
    const confirmedByName =
        payment?.confirmed_by_user?.name ??
        paymentUsers?.confirmed_by?.name ??
        paymentUsers?.confirmedBy?.name ??
        '—';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {payment?.invoice
                            ? formatPeriod(payment.invoice.period_start, 'id-ID')
                            : 'Payment'}
                    </SheetTitle>
                </SheetHeader>

                {payment && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Status
                                </h3>
                                <StatusBadge
                                    domain="payment"
                                    value={
                                        verifiedDate &&
                                        payment.status === 'confirmed'
                                            ? 'verified'
                                            : payment.status
                                    }
                                />
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Details
                                </h3>
                                <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                    <DetailRow
                                        label="Amount"
                                        value={formatPrice(payment.amount)}
                                    />
                                    <DetailRow
                                        label="Paid on"
                                        value={formatDate(payment.payment_date)}
                                    />
                                    <DetailRow
                                        label="Method"
                                        value={
                                            PAYMENT_METHOD_LABELS[
                                                payment.payment_method
                                            ] ?? payment.payment_method
                                        }
                                    />
                                    <DetailRow
                                        label="Reference"
                                        value={payment.invoice?.reference ?? '—'}
                                    />
                                    <DetailRow
                                        label="Confirmed by"
                                        value={confirmedByName}
                                    />
                                    <DetailRow
                                        label="Verified"
                                        value={formatDate(verifiedDate)}
                                    />
                                </div>
                            </section>

                            {payment.notes && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </h3>
                                    <div className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                        {payment.notes}
                                    </div>
                                </section>
                            )}

                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Proofs
                                </h3>
                                <div className="rounded-lg border p-4">
                                    {payment.proofs.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {payment.proofs.map((proof) => (
                                                <Button
                                                    key={proof.id}
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    onClick={() =>
                                                        onPreview(payment, proof)
                                                    }
                                                >
                                                    <FileText className="size-3" />
                                                    {proof.original_name}
                                                </Button>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No proofs uploaded.
                                        </p>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {payment.status === 'pending' && canVerify && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={verifyingId === payment.id}
                                        onClick={() =>
                                            onVerify(payment, 'confirm')
                                        }
                                    >
                                        <Check className="size-4" />
                                        Confirm
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={verifyingId === payment.id}
                                        onClick={() =>
                                            onVerify(payment, 'reject')
                                        }
                                    >
                                        <X className="size-4" />
                                        Reject
                                    </Button>
                                </>
                            )}

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
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-right text-sm">{value}</span>
        </div>
    );
}
