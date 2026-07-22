import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { show as showInvoice } from '@/routes/portal/billing/invoices';
import type { Invoice } from '@/types';

export default function InvoiceActionItem({
    invoice,
    onPay,
    amount = invoice.payable_amount ?? invoice.outstanding ?? '0',
}: {
    invoice: Invoice;
    onPay?: () => void;
    amount?: string;
}) {
    const status = invoice.display_status ?? invoice.status;

    return (
        <article className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="min-w-0 truncate font-medium">
                        {formatPeriod(invoice.period_start)} Rent
                    </p>
                    <StatusBadge domain="tenant_invoice" value={status} />
                </div>
                <p className="text-sm text-muted-foreground">
                    Due {formatDate(invoice.due_date)}
                    {invoice.reference && (
                        <span className="hidden sm:inline">
                            {' '}
                            · Invoice {invoice.reference}
                        </span>
                    )}
                </p>
                {invoice.reference && (
                    <p className="text-xs text-muted-foreground sm:hidden">
                        Invoice {invoice.reference}
                    </p>
                )}
            </div>

            <span className="shrink-0 font-medium tabular-nums">
                {formatPrice(amount)}
            </span>

            <div className="grid w-full gap-1 sm:flex sm:w-auto sm:items-center sm:gap-2">
                {onPay && (
                    <Button className="w-full sm:h-8 sm:w-auto" onClick={onPay}>
                        Pay invoice
                    </Button>
                )}
                <Button
                    variant="link"
                    className="h-10 w-fit px-0 sm:h-8 sm:px-2"
                    asChild
                >
                    <Link href={showInvoice(invoice)}>
                        View details <ChevronRight className="sm:hidden" />
                    </Link>
                </Button>
            </div>
        </article>
    );
}
