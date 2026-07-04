import { ChevronDown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease } from '@/types';

const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day',
};

export default function LeaseOverview({ lease }: { lease: Lease }) {
    const isActive = lease.status === 'active';
    const roomName = lease.room?.name ?? '—';
    const propertyName = lease.room?.property?.name ?? '—';
    const city = lease.room?.property?.city;
    const propertyCity = city && typeof city === 'object' ? city.name : city ?? '';

    return (
        <div className="space-y-6">
            <div>
                <Badge className={isActive ? 'bg-blue-600 text-white' : 'bg-gray-400 text-white'}>
                    {isActive ? 'Active' : 'Terminated'}
                </Badge>
            </div>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Occupancy</h3>
                        <ChevronDown className="size-3 text-muted-foreground transition-transform ui-open:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                            <div>
                                <p className="mb-2 text-xs text-muted-foreground">Tenants</p>
                                <div className="space-y-2">
                                    {(lease.tenants ?? []).length > 0
                                        ? lease.tenants.map((t) => (
                                            <div key={t.id} className="flex items-center justify-between">
                                                <span className="text-sm font-medium">
                                                    {t.name}
                                                    {t.pivot?.is_primary && (
                                                        <span className="ml-2 text-[10px] font-medium text-blue-600 uppercase">Primary</span>
                                                    )}
                                                </span>
                                                {t.phone && <span className="text-xs text-muted-foreground">{t.phone}</span>}
                                            </div>
                                        ))
                                        : lease.primary_tenant && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium">{lease.primary_tenant.name}</span>
                                                {lease.primary_tenant.phone && (
                                                    <span className="text-xs text-muted-foreground">{lease.primary_tenant.phone}</span>
                                                )}
                                            </div>
                                        )}
                                </div>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Room</span>
                                <span className="text-sm">{roomName}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Property</span>
                                <span className="text-sm">{propertyName}{propertyCity && ` — ${propertyCity}`}</span>
                            </div>
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Agreement</h3>
                        <ChevronDown className="size-3 text-muted-foreground transition-transform ui-open:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                            {lease.reference && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Reference</span>
                                    <span className="font-mono text-xs">{lease.reference}</span>
                                </div>
                            )}
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Start date</span>
                                <span className="tabular-nums">{formatDate(lease.start_date)}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">End date</span>
                                <span className="tabular-nums">
                                    {lease.termination_date ? formatDate(lease.termination_date) : formatDate(lease.end_date)}
                                </span>
                            </div>
                            {lease.termination_reason && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Reason</span>
                                    <span className="text-right text-sm capitalize">
                                        {lease.termination_reason.replace(/_/g, ' ')}
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
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Rent</h3>
                        <ChevronDown className="size-3 text-muted-foreground transition-transform ui-open:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Billing rate</span>
                                <span className="tabular-nums">{formatPrice(lease.rent_amount)}{lease.billing_label}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Monthly equivalent</span>
                                <span className="tabular-nums">{formatPrice(lease.monthly_equivalent)}/mo</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Due every month</span>
                                <span className="tabular-nums">
                                    {DUE_DAY_LABELS[lease.rent_due_day] ?? lease.rent_due_day}
                                </span>
                            </div>
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            <Collapsible defaultOpen>
                <div>
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Deposit</h3>
                        <ChevronDown className="size-3 text-muted-foreground transition-transform ui-open:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3">
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Amount</span>
                                <span className="tabular-nums">{formatPrice(lease.deposit_amount)}</span>
                            </div>
                            {lease.deposit_paid_at && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Paid at</span>
                                    <span className="tabular-nums">{formatDate(lease.deposit_paid_at)}</span>
                                </div>
                            )}
                            {lease.deposit_refund_amount && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Refund</span>
                                    <span className="tabular-nums">{formatPrice(lease.deposit_refund_amount)}</span>
                                </div>
                            )}
                            {lease.deposit_refunded_at && (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Refunded at</span>
                                    <span className="tabular-nums">{formatDate(lease.deposit_refunded_at)}</span>
                                </div>
                            )}
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>

            {lease.notes && (
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">Notes</p>
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">{lease.notes}</p>
                </div>
            )}
        </div>
    );
}
