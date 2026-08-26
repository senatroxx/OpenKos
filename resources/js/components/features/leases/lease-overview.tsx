import { ChevronDown } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { DUE_DAY_LABELS } from '@/lib/constants';
import { BILLING_STRATEGIES } from '@/lib/constants/billing';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { Lease } from '@/types';

export default function LeaseOverview({ lease }: { lease: Lease }) {
    const unitLabel = lease.unit?.name ?? '—';
    const propertyName = lease.unit?.property?.name ?? '—';
    const city = lease.unit?.property?.city;
    const propertyCity =
        city && typeof city === 'object' ? city.name : (city ?? '');
    const billingStrategy =
        BILLING_STRATEGIES.find((s) => s.value === lease.billing_strategy)
            ?.label ?? 'Advance (due within period)';

    return (
        <div className="space-y-6">
            <div>
                <StatusBadge status={lease.status} />
            </div>

            <Collapsible defaultOpen>
                <div>
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
                                    {(lease.tenants ?? []).length > 0
                                        ? lease.tenants.map((tenant) => (
                                              <div
                                                  key={tenant.id}
                                                  className="flex items-center justify-between"
                                              >
                                                  <span className="text-sm font-medium">
                                                      {tenant.name}
                                                      {tenant.pivot
                                                          ?.is_primary && (
                                                          <span className="ml-2 text-xs font-medium text-primary uppercase">
                                                              {t('Primary')}
                                                          </span>
                                                      )}
                                                  </span>
                                                  {tenant.phone && (
                                                      <span className="text-xs text-muted-foreground">
                                                          {tenant.phone}
                                                      </span>
                                                  )}
                                              </div>
                                          ))
                                        : lease.primary_tenant && (
                                              <div className="flex items-center justify-between">
                                                  <span className="text-sm font-medium">
                                                      {
                                                          lease.primary_tenant
                                                              .name
                                                      }
                                                  </span>
                                                  {lease.primary_tenant
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
                                <span className="text-sm">{unitLabel}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('Property')}
                                </span>
                                <span className="text-sm">
                                    {propertyName}
                                    {propertyCity && ` — ${propertyCity}`}
                                </span>
                            </div>
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            {t('Agreement')}
                        </h3>
                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
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
                                    {formatDate(lease.start_date)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {t('End date')}
                                </span>
                                <span className="tabular-nums">
                                    {lease.termination_date
                                        ? formatDate(lease.termination_date)
                                        : formatDate(lease.end_date)}
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
                </div>
            </Collapsible>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            {t('Rent')}
                        </h3>
                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
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
                                    {t(billingStrategy)}
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
                                    {DUE_DAY_LABELS[lease.rent_due_day] ??
                                        lease.rent_due_day}
                                </span>
                            </div>
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            {t('Deposit')}
                        </h3>
                        <ChevronDown className="ui-open:rotate-180 size-3 text-muted-foreground transition-transform" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
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
                                        {formatDate(lease.deposit_paid_at)}
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
                                        {formatDate(lease.deposit_refunded_at)}
                                    </span>
                                </div>
                            )}
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            {lease.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Notes')}
                    </p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                        {lease.notes}
                    </p>
                </div>
            )}
        </div>
    );
}
