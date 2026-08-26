import { router, usePage } from '@inertiajs/react';
import { Banknote, ChevronDown, FileText } from 'lucide-react';
import { useState } from 'react';
import { RecordPaymentSheet } from '@/components/features';
import { DocumentPreview } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { DUE_DAY_LABELS } from '@/lib/constants';
import {
    BILLING_STRATEGIES,
    PAYMENT_METHOD_LABELS,
} from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import leases from '@/routes/leases';
import type { Lease, Payment } from '@/types';

export default function LeaseDetailSheet({
    lease,
    open,
    onOpenChange,
    onMoveOut,
    onMoveUnit,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onMoveOut?: () => void;
    onMoveUnit?: () => void;
}) {
    const { auth } = usePage<{ auth: { permissions: string[] } }>().props;
    const [recordPaymentOpen, setRecordPaymentOpen] = useState(false);
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [previewProof, setPreviewProof] = useState<{
        src: string;
        mimeType: string;
        name: string;
    } | null>(null);
    const isActive = lease?.status === 'active';
    const payments = (lease?.payments ?? []) as Payment[];
    const canVerify = auth.permissions.includes('payments.verify');
    const canSendReminder = auth.permissions.includes('reminders.send');

    function handleVerify(payment: Payment, action: 'confirm' | 'reject') {
        setVerifyingId(payment.id);
        router.post(
            `/payments/${payment.id}/verify`,
            { action } as Record<string, string>,
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setVerifyingId(null),
            },
        );
    }

    const unitLabel = lease?.unit?.name ?? '—';
    const propertyName = lease?.unit?.property?.name ?? '—';
    const city = lease?.unit?.property?.city;
    const propertyCity =
        city && typeof city === 'object' ? city.name : (city ?? '');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="sm:max-w-lg"
                expandTo={lease ? leases.show.url(lease) : undefined}
            >
                <SheetHeader>
                    <SheetTitle>
                        {isActive ? t('Active Lease') : t('Lease Details')}
                    </SheetTitle>
                </SheetHeader>

                {lease && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {t('Status')}
                                </h3>
                                <StatusBadge
                                    domain="lease"
                                    value={lease.status}
                                />
                            </section>

                            {/* Occupancy */}
                            <Collapsible defaultOpen>
                                <section>
                                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {t('Occupancy')}
                                        </h3>
                                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="mt-3">
                                        <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                            <div>
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    {t('Tenants')}
                                                </p>
                                                <div className="space-y-2">
                                                    {(lease?.tenants ?? [])
                                                        .length > 0
                                                        ? lease!.tenants.map(
                                                              (tenant) => (
                                                                  <div
                                                                      key={
                                                                          tenant.id
                                                                      }
                                                                      className="flex items-center justify-between"
                                                                  >
                                                                      <div>
                                                                          <span className="text-sm font-medium">
                                                                              {
                                                                                  tenant.name
                                                                              }
                                                                          </span>
                                                                          {tenant
                                                                              .pivot
                                                                              ?.is_primary && (
                                                                              <span className="ml-2 text-xs font-medium text-primary uppercase">
                                                                                  {t(
                                                                                      'Primary',
                                                                                  )}
                                                                              </span>
                                                                          )}
                                                                      </div>
                                                                      {tenant.phone && (
                                                                          <span className="text-xs text-muted-foreground">
                                                                              {
                                                                                  tenant.phone
                                                                              }
                                                                          </span>
                                                                      )}
                                                                  </div>
                                                              ),
                                                          )
                                                        : lease?.primary_tenant && (
                                                              <div className="flex items-center justify-between">
                                                                  <span className="text-sm font-medium">
                                                                      {
                                                                          lease
                                                                              .primary_tenant
                                                                              .name
                                                                      }
                                                                  </span>
                                                                  {lease
                                                                      .primary_tenant
                                                                      .phone && (
                                                                      <span className="text-xs text-muted-foreground">
                                                                          {
                                                                              lease
                                                                                  .primary_tenant
                                                                                  .phone
                                                                          }
                                                                      </span>
                                                                  )}
                                                              </div>
                                                          )}
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">
                                                    {t('Unit')}
                                                </span>
                                                <span className="text-sm">
                                                    {unitLabel}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">
                                                    {t('Property')}
                                                </span>
                                                <span className="text-sm">
                                                    {propertyName}
                                                    {propertyCity &&
                                                        ` — ${propertyCity}`}
                                                </span>
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </section>
                            </Collapsible>

                            {/* Agreement */}
                            <Collapsible defaultOpen>
                                <section>
                                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {t('Agreement')}
                                        </h3>
                                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="mt-3">
                                        <div className="space-y-2 rounded-lg border p-4">
                                            {lease.reference && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {t('Reference')}
                                                    </span>
                                                    <span className="font-mono text-xs">
                                                        {lease.reference}
                                                    </span>
                                                </div>
                                            )}
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Start date')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {formatDate(
                                                        lease.start_date,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('End date')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {lease.termination_date
                                                        ? formatDate(
                                                              lease.termination_date,
                                                          )
                                                        : formatDate(
                                                              lease.end_date,
                                                          )}
                                                </span>
                                            </div>
                                            {lease.termination_reason && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {t('Reason')}
                                                    </span>
                                                    <span className="text-right text-sm capitalize">
                                                        {lease.termination_reason.replace(
                                                            /_/g,
                                                            ' ',
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CollapsibleContent>
                                </section>
                            </Collapsible>

                            {/* Rent */}
                            <Collapsible defaultOpen>
                                <section>
                                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {t('Rent')}
                                        </h3>
                                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="mt-3">
                                        <div className="space-y-2 rounded-lg border p-4">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Billing rate')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {formatPrice(
                                                        lease.rent_amount,
                                                        lease.currency,
                                                    )}
                                                    {lease.billing_label}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Billing strategy')}
                                                </span>
                                                <span className="text-xs font-medium">
                                                    {t(
                                                        BILLING_STRATEGIES.find(
                                                            (s) =>
                                                                s.value ===
                                                                lease.billing_strategy,
                                                        )?.label ??
                                                            'Advance (due within period)',
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Monthly equivalent')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {formatPrice(
                                                        lease.monthly_equivalent,
                                                        lease.currency,
                                                    )}
                                                    /mo
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Due every month')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {DUE_DAY_LABELS[
                                                        lease.rent_due_day
                                                    ] ?? lease.rent_due_day}
                                                </span>
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </section>
                            </Collapsible>

                            {/* Deposit */}
                            <Collapsible defaultOpen>
                                <section>
                                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {t('Deposit')}
                                        </h3>
                                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="mt-3">
                                        <div className="space-y-2 rounded-lg border p-4">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t('Amount')}
                                                </span>
                                                <span className="tabular-nums">
                                                    {formatPrice(
                                                        lease.deposit_amount,
                                                        lease.currency,
                                                    )}
                                                </span>
                                            </div>
                                            {lease.deposit_paid_at && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {t('Paid at')}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {formatDate(
                                                            lease.deposit_paid_at,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                            {lease.deposit_refund_amount && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {t('Refund')}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {formatPrice(
                                                            lease.deposit_refund_amount,
                                                            lease.currency,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                            {lease.deposit_refunded_at && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        {t('Refunded at')}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {formatDate(
                                                            lease.deposit_refunded_at,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CollapsibleContent>
                                </section>
                            </Collapsible>

                            {/* Payment History */}
                            <Collapsible defaultOpen>
                                <section>
                                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {t('Payment History')}
                                        </h3>
                                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="mt-3">
                                        {payments.length === 0 ? (
                                            <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                                                {t('No payments recorded yet.')}
                                            </p>
                                        ) : (
                                            <div className="space-y-2">
                                                {payments.map((payment) => (
                                                    <div
                                                        key={payment.id}
                                                        className="rounded-lg border p-3 text-sm"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <p className="font-medium">
                                                                    {payment.invoice
                                                                        ? formatPeriod(
                                                                              payment
                                                                                  .invoice
                                                                                  .period_start,
                                                                          )
                                                                        : '—'}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {PAYMENT_METHOD_LABELS[
                                                                        payment
                                                                            .payment_method
                                                                    ] ??
                                                                        payment.payment_method}
                                                                    {payment.confirmed_by_user &&
                                                                        ` · by ${payment.confirmed_by_user.name}`}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-medium tabular-nums">
                                                                    {formatPrice(
                                                                        payment.amount,
                                                                        payment.currency,
                                                                    )}
                                                                </p>
                                                                <StatusBadge
                                                                    domain="payment"
                                                                    value={
                                                                        payment.verified_at &&
                                                                        payment.status ===
                                                                            'confirmed'
                                                                            ? 'verified'
                                                                            : payment.status
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                        {payment.proofs
                                                            ?.length > 0 && (
                                                            <div className="mt-2 flex flex-wrap gap-2 border-t pt-2">
                                                                {(
                                                                    payment.proofs ??
                                                                    []
                                                                ).map(
                                                                    (proof) => (
                                                                        <button
                                                                            key={
                                                                                proof.id
                                                                            }
                                                                            type="button"
                                                                            onClick={() =>
                                                                                setPreviewProof(
                                                                                    {
                                                                                        src: `/payments/${payment.id}/proof/${proof.id}`,
                                                                                        mimeType:
                                                                                            proof.mime_type,
                                                                                        name: proof.original_name,
                                                                                    },
                                                                                )
                                                                            }
                                                                            className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                                                                        >
                                                                            <FileText className="size-3" />
                                                                            {
                                                                                proof.original_name
                                                                            }
                                                                        </button>
                                                                    ),
                                                                )}
                                                                {payment.status ===
                                                                    'pending' &&
                                                                    canVerify && (
                                                                        <div className="ml-auto flex gap-1">
                                                                            <Button
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="h-6 px-2 text-xs"
                                                                                disabled={
                                                                                    verifyingId ===
                                                                                    payment.id
                                                                                }
                                                                                onClick={(
                                                                                    e,
                                                                                ) => {
                                                                                    e.preventDefault();
                                                                                    handleVerify(
                                                                                        payment,
                                                                                        'confirm',
                                                                                    );
                                                                                }}
                                                                            >
                                                                                {t(
                                                                                    'Verify',
                                                                                )}
                                                                            </Button>
                                                                            <Button
                                                                                size="sm"
                                                                                variant="destructive"
                                                                                className="h-6 px-2 text-xs"
                                                                                disabled={
                                                                                    verifyingId ===
                                                                                    payment.id
                                                                                }
                                                                                onClick={(
                                                                                    e,
                                                                                ) => {
                                                                                    e.preventDefault();
                                                                                    handleVerify(
                                                                                        payment,
                                                                                        'reject',
                                                                                    );
                                                                                }}
                                                                            >
                                                                                {t(
                                                                                    'Reject',
                                                                                )}
                                                                            </Button>
                                                                        </div>
                                                                    )}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CollapsibleContent>
                                </section>
                            </Collapsible>

                            {/* Notes */}
                            {lease.notes && (
                                <Collapsible defaultOpen>
                                    <section>
                                        <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                {t('Notes')}
                                            </h3>
                                            <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                        </CollapsibleTrigger>
                                        <CollapsibleContent className="mt-3">
                                            <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                                {lease.notes}
                                            </p>
                                        </CollapsibleContent>
                                    </section>
                                </Collapsible>
                            )}

                            {/* Unit History */}
                            {lease.unit_histories &&
                                lease.unit_histories.length > 0 && (
                                    <Collapsible defaultOpen>
                                        <section>
                                            <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                                                <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                    {t('Unit History')}
                                                </h3>
                                                <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="mt-3">
                                                <div className="rounded-lg border">
                                                    {lease.unit_histories.map(
                                                        (h, i) => (
                                                            <div
                                                                key={h.id}
                                                                className={`flex items-start gap-3 p-3 text-sm ${
                                                                    i > 0
                                                                        ? 'border-t'
                                                                        : ''
                                                                }`}
                                                            >
                                                                <div className="mt-0.5 size-2 shrink-0 rounded-full bg-muted-foreground/30" />
                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <span className="font-medium">
                                                                            {h
                                                                                .from_unit
                                                                                ?.name ??
                                                                                '—'}
                                                                        </span>
                                                                        <span className="text-muted-foreground">
                                                                            →
                                                                        </span>
                                                                        <span className="font-medium">
                                                                            {h
                                                                                .to_unit
                                                                                ?.name ??
                                                                                '—'}
                                                                        </span>
                                                                    </div>
                                                                    <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                                        {h.reason && (
                                                                            <Badge
                                                                                variant="outline"
                                                                                className="h-4 px-1.5 py-0 text-xs"
                                                                            >
                                                                                {
                                                                                    h.reason
                                                                                }
                                                                            </Badge>
                                                                        )}
                                                                        <span>
                                                                            {formatDate(
                                                                                h.effective_date,
                                                                            )}
                                                                        </span>
                                                                        {h.transferred_by && (
                                                                            <span>
                                                                                {t(
                                                                                    'by',
                                                                                )}{' '}
                                                                                {
                                                                                    h
                                                                                        .transferred_by
                                                                                        .name
                                                                                }
                                                                                {h
                                                                                    .transferred_by
                                                                                    .roles?.[0]
                                                                                    ? ` — ${h.transferred_by.roles[0].label ?? h.transferred_by.roles[0].name}`
                                                                                    : ''}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </CollapsibleContent>
                                        </section>
                                    </Collapsible>
                                )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {isActive && (
                                <Button
                                    variant="default"
                                    onClick={() => {
                                        onOpenChange(false);
                                        setRecordPaymentOpen(true);
                                    }}
                                >
                                    <Banknote className="mr-1.5 size-4" />
                                    {t('Record Payment')}
                                </Button>
                            )}
                            {isActive && onMoveOut && (
                                <Button
                                    variant="destructive"
                                    onClick={onMoveOut}
                                >
                                    {t('Move Out Tenant')}
                                </Button>
                            )}
                            {isActive && onMoveUnit && (
                                <Button variant="outline" onClick={onMoveUnit}>
                                    {t('Move Unit')}
                                </Button>
                            )}
                            {isActive && canSendReminder && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/leases/${lease.id}/send-reminder`,
                                        )
                                    }
                                >
                                    {t('Send Reminder')}
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                {t('Close')}
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>

            <RecordPaymentSheet
                lease={lease}
                open={recordPaymentOpen}
                onOpenChange={setRecordPaymentOpen}
            />

            {previewProof && (
                <DocumentPreview
                    src={previewProof.src}
                    mimeType={previewProof.mimeType}
                    title={previewProof.name}
                    subtitle={t('Payment Proof')}
                    onClose={() => setPreviewProof(null)}
                />
            )}
        </Sheet>
    );
}
