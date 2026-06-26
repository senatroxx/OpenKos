import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

import type { Lease } from '@/types';

function formatPrice(cents: string | null): string {
    if (!cents) {
        return '—';
    }

    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day',
};

export default function LeaseDetailSheet({
    lease,
    open,
    onOpenChange,
    onMoveOut,
    onMoveRoom,
    onEdit,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onMoveOut?: () => void;
    onMoveRoom?: () => void;
    onEdit?: () => void;
}) {
    const isActive = lease?.status === 'active';
    const roomName = lease?.room?.name ?? '—';
    const propertyName = lease?.room?.property?.name ?? '—';
    const propertyCity = lease?.room?.property?.city?.name ?? '';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isActive ? 'Active Lease' : 'Lease Details'}
                    </SheetTitle>
                </SheetHeader>

                {lease && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            {/* Status */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Status
                                </h3>
                                <Badge
                                    className={
                                        isActive
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-gray-400 text-white'
                                    }
                                >
                                    {isActive ? 'Active' : 'Terminated'}
                                </Badge>
                            </section>

                            {/* Occupancy */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Occupancy
                                </h3>
                                <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                    <div>
                                        <p className="mb-2 text-xs text-muted-foreground">
                                            Tenants
                                        </p>
                                        <div className="space-y-2">
                                            {(lease?.tenants ?? []).length > 0
                                                ? lease!.tenants.map((t) => (
                                                      <div
                                                          key={t.id}
                                                          className="flex items-center justify-between"
                                                      >
                                                          <div>
                                                              <span className="text-sm font-medium">
                                                                  {t.name}
                                                              </span>
                                                              {t.pivot
                                                                  ?.is_primary && (
                                                                  <span className="ml-2 text-[10px] font-medium text-blue-600 uppercase">
                                                                      Primary
                                                                  </span>
                                                              )}
                                                          </div>
                                                          {t.phone && (
                                                              <span className="text-xs text-muted-foreground">
                                                                  {t.phone}
                                                              </span>
                                                          )}
                                                      </div>
                                                  ))
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
                                            Room
                                        </span>
                                        <span className="text-sm">
                                            {roomName}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">
                                            Property
                                        </span>
                                        <span className="text-sm">
                                            {propertyName}
                                            {propertyCity &&
                                                ` — ${propertyCity}`}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            {/* Agreement */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Agreement
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Start date
                                        </span>
                                        <span className="tabular-nums">
                                            {formatDate(lease.start_date)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            End date
                                        </span>
                                        <span className="tabular-nums">
                                            {lease.termination_date
                                                ? formatDate(
                                                      lease.termination_date,
                                                  )
                                                : formatDate(lease.end_date)}
                                        </span>
                                    </div>
                                    {lease.termination_reason && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Reason
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
                            </section>

                            {/* Rent */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Rent
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Billing rate
                                        </span>
                                        <span className="tabular-nums">
                                            {formatPrice(lease.rent_amount)}
                                            {lease.billing_label}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Monthly equivalent
                                        </span>
                                        <span className="tabular-nums">
                                            {formatPrice(
                                                lease.monthly_equivalent,
                                            )}
                                            /mo
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Due every month
                                        </span>
                                        <span className="tabular-nums">
                                            {DUE_DAY_LABELS[
                                                lease.rent_due_day
                                            ] ?? lease.rent_due_day}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            {/* Deposit */}
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Deposit
                                </h3>
                                <div className="space-y-2 rounded-lg border p-4">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Amount
                                        </span>
                                        <span className="tabular-nums">
                                            {formatPrice(lease.deposit_amount)}
                                        </span>
                                    </div>
                                    {lease.deposit_paid_at && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Paid at
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
                                                Refund
                                            </span>
                                            <span className="tabular-nums">
                                                {formatPrice(
                                                    lease.deposit_refund_amount,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {lease.deposit_refunded_at && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Refunded at
                                            </span>
                                            <span className="tabular-nums">
                                                {formatDate(
                                                    lease.deposit_refunded_at,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {/* Notes */}
                            {lease.notes && (
                                <section>
                                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </h3>
                                    <p className="rounded-lg border p-4 text-sm whitespace-pre-wrap">
                                        {lease.notes}
                                    </p>
                                </section>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            {onEdit && (
                                <Button variant="outline" onClick={onEdit}>
                                    Edit
                                </Button>
                            )}
                            {isActive && onMoveOut && (
                                <Button
                                    variant="destructive"
                                    onClick={onMoveOut}
                                >
                                    Move Out Tenant
                                </Button>
                            )}
                            {isActive && onMoveRoom && (
                                <Button variant="outline" onClick={onMoveRoom}>
                                    Move Room
                                </Button>
                            )}
                            <Button
                                variant="ghost"
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
