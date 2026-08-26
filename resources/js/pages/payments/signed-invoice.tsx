import { Head } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Clock } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { GatewayPaymentAttempt, InvoiceLineItem } from '@/types';

type PublicInvoice = {
    reference: string | null;
    period_start: string;
    period_end: string;
    due_date: string;
    status: string;
    display_status: string;
    total: string;
    amount_paid: string;
    outstanding: string;
    currency: string;
    context: {
        property_name: string | null;
        unit_name: string | null;
        tenant_name: string | null;
    };
    line_items: Array<
        Pick<InvoiceLineItem, 'id' | 'type' | 'description' | 'amount'>
    >;
};

export default function SignedInvoice({
    invoice,
    gatewayAttempts,
    onlinePaymentAvailable,
    paymentUrl,
    csrfToken,
}: {
    invoice: PublicInvoice;
    gatewayAttempts: GatewayPaymentAttempt[];
    onlinePaymentAvailable: boolean;
    paymentUrl: string;
    csrfToken: string;
}) {
    const isPayable = ['pending', 'partial'].includes(invoice.status);
    const resumableAttempt = gatewayAttempts.find(
        (attempt) => attempt.resumable,
    );
    const latestAttempt = gatewayAttempts[0];

    return (
        <div className="space-y-6">
            <Head title={`${t('Invoice')} ${invoice.reference ?? ''}`} />

            <header className="space-y-2">
                <p className="text-sm text-muted-foreground">
                    {t('Payment link')}
                </p>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {invoice.reference ?? t('Invoice')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {formatPeriod(invoice.period_start)}
                        </p>
                    </div>
                    <StatusBadge
                        domain="invoice"
                        value={invoice.display_status}
                    />
                </div>
            </header>

            {invoice.status === 'paid' ? (
                <Alert>
                    <AlertTitle>{t('Invoice paid')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'This invoice is paid. No further payment is required.',
                        )}
                    </AlertDescription>
                </Alert>
            ) : latestAttempt?.status === 'pending' ? (
                <Alert>
                    <Clock />
                    <AlertTitle>{t('Checkout in progress')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'Complete the checkout or return here later. The invoice updates after the payment provider confirms payment.',
                        )}
                    </AlertDescription>
                </Alert>
            ) : latestAttempt?.status === 'failed' ||
              latestAttempt?.status === 'expired' ? (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>{t('Checkout unavailable')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'The previous checkout is no longer available. You can start a new payment below.',
                        )}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div className="space-y-6">
                    {(invoice.context.tenant_name ||
                        invoice.context.unit_name ||
                        invoice.context.property_name) && (
                        <section className="rounded-lg border p-4">
                            <h2 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Payment context')}
                            </h2>
                            <div className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                {invoice.context.tenant_name && (
                                    <Detail
                                        label={t('Billed to')}
                                        value={invoice.context.tenant_name}
                                    />
                                )}
                                {invoice.context.property_name && (
                                    <Detail
                                        label={t('Property')}
                                        value={invoice.context.property_name}
                                    />
                                )}
                                {invoice.context.unit_name && (
                                    <Detail
                                        label={t('Unit')}
                                        value={invoice.context.unit_name}
                                    />
                                )}
                            </div>
                        </section>
                    )}

                    <section className="rounded-lg border">
                        <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            {t('Invoice details')}
                        </h2>
                        {invoice.line_items.length > 0 ? (
                            <div className="divide-y">
                                {invoice.line_items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                                    >
                                        <div>
                                            <p>{item.description}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.type}
                                            </p>
                                        </div>
                                        <span className="tabular-nums">
                                            {formatPrice(
                                                item.amount,
                                                invoice.currency,
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="px-4 py-6 text-sm text-muted-foreground">
                                {t('No itemized charges.')}
                            </p>
                        )}
                    </section>

                    {gatewayAttempts.length > 0 && (
                        <section className="rounded-lg border">
                            <div className="border-b px-4 py-3">
                                <h2 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Online payment attempts')}
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'Payment status is updated after provider confirmation.',
                                    )}
                                </p>
                            </div>
                            <div className="divide-y">
                                {gatewayAttempts.map((attempt) => (
                                    <GatewayAttemptRow
                                        key={attempt.id}
                                        attempt={attempt}
                                    />
                                ))}
                            </div>
                        </section>
                    )}
                </div>

                <aside className="order-first rounded-lg border p-5 lg:sticky lg:top-8 lg:order-none">
                    <p className="text-sm text-muted-foreground">
                        {t('Outstanding balance')}
                    </p>
                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                        {formatPrice(invoice.outstanding, invoice.currency)}
                    </p>
                    <div className="mt-5 grid gap-4 text-sm">
                        <Detail
                            label={t('Total')}
                            value={formatPrice(invoice.total, invoice.currency)}
                        />
                        <Detail
                            label={t('Paid')}
                            value={formatPrice(
                                invoice.amount_paid,
                                invoice.currency,
                            )}
                        />
                        <Detail
                            label={t('Due date')}
                            value={formatDate(invoice.due_date)}
                        />
                    </div>
                    {isPayable && onlinePaymentAvailable && (
                        <form action={paymentUrl} method="post" target="_blank">
                            <input
                                type="hidden"
                                name="_token"
                                value={csrfToken}
                            />
                            <Button className="mt-6 w-full" type="submit">
                                {resumableAttempt
                                    ? t('Continue online payment')
                                    : t('Pay online')}
                                <ArrowRight />
                            </Button>
                        </form>
                    )}
                    {isPayable && !onlinePaymentAvailable && (
                        <p className="mt-6 text-sm text-muted-foreground">
                            {t('Online payment is currently unavailable.')}
                        </p>
                    )}
                </aside>
            </div>

            <p className="text-center text-xs text-muted-foreground">
                {t(
                    'Payment status is confirmed by the payment provider and may take a moment to appear here.',
                )}
            </p>
        </div>
    );
}

function GatewayAttemptRow({ attempt }: { attempt: GatewayPaymentAttempt }) {
    return (
        <div className="space-y-3 px-4 py-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="font-medium">
                        {t('Started')} {formatDate(attempt.initiated_at)}
                    </p>
                    {attempt.status === 'pending' && attempt.expires_at && (
                        <p className="text-xs text-muted-foreground">
                            {t('Expires')} {formatDate(attempt.expires_at)}
                        </p>
                    )}
                </div>
                <StatusBadge domain="gateway_attempt" value={attempt.status} />
            </div>

            {attempt.checkout_instructions && (
                <div className="space-y-2 text-xs">
                    {attempt.checkout_instructions.entries.map((entry) => (
                        <div
                            key={`${entry.key}-${entry.value}`}
                            className="flex flex-wrap justify-between gap-3"
                        >
                            <span className="text-muted-foreground">
                                {entry.label ?? entry.key}
                            </span>
                            <span className="font-medium break-all">
                                {entry.value}
                            </span>
                        </div>
                    ))}
                    {attempt.resumable && attempt.checkout_instructions.url && (
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={attempt.checkout_instructions.url}
                                rel="noreferrer"
                                target="_blank"
                            >
                                {t('Continue checkout')}
                            </a>
                        </Button>
                    )}
                </div>
            )}
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
