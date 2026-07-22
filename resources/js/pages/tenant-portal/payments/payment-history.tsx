import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import PortalHistoryPagination from '@/components/features/payments/portal-history-pagination';
import TenantLeaseContext from '@/components/features/tenant-portal/lease-context';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { index as billingIndex } from '@/routes/portal/billing';
import { payments as paymentHistory } from '@/routes/portal/billing/history';
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

export default function PaymentHistory({
    payments,
    leaseContext,
}: {
    payments: PaginatedData<PortalPayment>;
    leaseContext: LeaseContext;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <Head title="Payment history" />

            <Link
                href={billingIndex({
                    query: { lease: leaseContext.selected?.id },
                })}
                className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to billing
            </Link>

            <div>
                <h1 className="text-2xl font-semibold">Payment history</h1>
                <p className="text-sm text-muted-foreground">
                    Confirmed and cancelled payment submissions.
                </p>
            </div>

            <TenantLeaseContext
                leaseContext={leaseContext}
                hrefForLease={(leaseId) =>
                    paymentHistory({ query: { lease: leaseId } }).url
                }
            />

            {payments.data.length === 0 ? (
                <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                    No finalized payments yet.
                </p>
            ) : (
                <div className="divide-y rounded-lg border">
                    {payments.data.map((payment) => (
                        <div
                            key={payment.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {payment.invoice.reference ??
                                        formatPeriod(
                                            payment.invoice.period_start,
                                        )}
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
                                <StatusBadge
                                    domain="tenant_payment"
                                    value={payment.status}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <PortalHistoryPagination
                currentPage={payments.current_page}
                lastPage={payments.last_page}
                previousHref={
                    payments.current_page > 1
                        ? paymentHistory({
                              query: {
                                  payment_page: payments.current_page - 1,
                                  lease: leaseContext.selected?.id,
                              },
                          }).url
                        : null
                }
                nextHref={
                    payments.current_page < payments.last_page
                        ? paymentHistory({
                              query: {
                                  payment_page: payments.current_page + 1,
                                  lease: leaseContext.selected?.id,
                              },
                          }).url
                        : null
                }
            />
        </div>
    );
}
