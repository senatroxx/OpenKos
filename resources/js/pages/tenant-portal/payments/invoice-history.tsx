import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import InvoiceActionItem from '@/components/features/payments/invoice-action-item';
import PortalHistoryPagination from '@/components/features/payments/portal-history-pagination';
import TenantLeaseContext from '@/components/features/tenant-portal/lease-context';
import { index as billingIndex } from '@/routes/portal/billing';
import { invoices as invoiceHistory } from '@/routes/portal/billing/history';
import type {
    Invoice,
    PaginatedData,
    TenantLeaseContext as LeaseContext,
} from '@/types';

export default function InvoiceHistory({
    invoices,
    leaseContext,
}: {
    invoices: PaginatedData<Invoice>;
    leaseContext: LeaseContext;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <Head title="Invoice history" />

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
                <h1 className="text-2xl font-semibold">Invoice history</h1>
                <p className="text-sm text-muted-foreground">
                    Completed and closed invoices.
                </p>
            </div>

            <TenantLeaseContext
                leaseContext={leaseContext}
                hrefForLease={(leaseId) =>
                    invoiceHistory({ query: { lease: leaseId } }).url
                }
            />

            {invoices.data.length === 0 ? (
                <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                    No completed invoices yet.
                </p>
            ) : (
                <div className="divide-y rounded-lg border">
                    {invoices.data.map((invoice) => (
                        <InvoiceActionItem
                            key={invoice.id}
                            invoice={invoice}
                            amount={invoice.total}
                        />
                    ))}
                </div>
            )}

            <PortalHistoryPagination
                currentPage={invoices.current_page}
                lastPage={invoices.last_page}
                previousHref={
                    invoices.current_page > 1
                        ? invoiceHistory({
                              query: {
                                  invoice_page: invoices.current_page - 1,
                                  lease: leaseContext.selected?.id,
                              },
                          }).url
                        : null
                }
                nextHref={
                    invoices.current_page < invoices.last_page
                        ? invoiceHistory({
                              query: {
                                  invoice_page: invoices.current_page + 1,
                                  lease: leaseContext.selected?.id,
                              },
                          }).url
                        : null
                }
            />
        </div>
    );
}
