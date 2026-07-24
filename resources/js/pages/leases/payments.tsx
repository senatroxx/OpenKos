import { Link, router, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { PaymentDetailSheet } from '@/components/features';
import { DocumentPreview } from '@/components/shared';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Button } from '@/components/ui/button';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import leaseInvoices from '@/routes/leases/workspace/invoices';
import paymentRoutes from '@/routes/payments';
import type {
    PaginatedData,
    Payment,
    PaymentProof,
    TableMeta,
    WorkspaceLease,
} from '@/types';
import { LeaseLayout } from './layout';

export default function LeasePayments({
    lease,
    payments,
    sort = '-payment_date',
    search = '',
    status = '',
    payment_method = '',
    per_page = 15,
    table,
}: {
    lease: WorkspaceLease;
    payments: PaginatedData<Payment>;
    sort?: string;
    search?: string;
    status?: string;
    payment_method?: string;
    per_page?: number;
    table: TableMeta;
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
    const selectedPayment =
        payments.data.find((payment) => payment.id === selectedPaymentId) ??
        null;

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

    function openDetail(payment: Payment) {
        setSelectedPaymentId(payment.id);
        setDetailOpen(true);
    }

    const columns: TableColumn<Payment>[] = [
        {
            key: 'period_start',
            label: 'Period',
            className: 'font-medium',
            render: (payment) =>
                payment.invoice
                    ? formatPeriod(payment.invoice.period_start, 'id-ID')
                    : '—',
        },
        {
            key: 'payment_date',
            label: 'Paid on',
            sortable: true,
            className: 'text-muted-foreground tabular-nums',
            render: (payment) =>
                payment.payment_date ? formatDate(payment.payment_date) : '—',
        },
        {
            key: 'amount',
            label: 'Amount',
            sortable: true,
            className: 'tabular-nums',
            render: (payment) => formatPrice(payment.amount),
        },
        {
            key: 'payment_method',
            label: 'Method',
            sortable: true,
            className: 'text-muted-foreground',
            render: (payment) =>
                PAYMENT_METHOD_LABELS[payment.payment_method] ??
                payment.payment_method,
        },
        {
            key: 'reference',
            label: 'Reference',
            sortable: true,
            className: 'font-mono text-xs text-muted-foreground',
            render: (payment) =>
                payment.invoice ? (
                    <Link
                        href={leaseInvoices.show.url([lease, payment.invoice])}
                        onClick={(event) => event.stopPropagation()}
                        className="text-blue-600 hover:underline"
                    >
                        {payment.invoice.reference ?? '—'}
                    </Link>
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (payment) => (
                <StatusBadge
                    domain="payment"
                    value={
                        payment.verified_at && payment.status === 'confirmed'
                            ? 'verified'
                            : payment.status
                    }
                />
            ),
        },
        {
            key: '_actions',
            label: 'Actions',
            render: (payment) =>
                payment.status === 'pending' && canVerify ? (
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="xs"
                            variant="outline"
                            disabled={verifyingId === payment.id}
                            onClick={(event) => {
                                event.stopPropagation();
                                handleVerify(payment, 'confirm');
                            }}
                        >
                            <Check className="size-3" />
                            Confirm
                        </Button>
                        <Button
                            type="button"
                            size="xs"
                            variant="destructive"
                            disabled={verifyingId === payment.id}
                            onClick={(event) => {
                                event.stopPropagation();
                                handleVerify(payment, 'reject');
                            }}
                        >
                            <X className="size-3" />
                            Reject
                        </Button>
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <LeaseLayout lease={lease} activeTab="payments">
            <PluginRegion name="workspace-tab-payments">
                <WorkspaceTable
                    url={`/leases/${lease.id}/payments`}
                    noun="payments"
                    rows={payments}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ status, payment_method }}
                    defaultSort="-payment_date"
                    searchPlaceholder="Search by invoice reference..."
                    emptyMessage="No payments recorded yet."
                    onRowClick={openDetail}
                />
            </PluginRegion>

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
        </LeaseLayout>
    );
}
