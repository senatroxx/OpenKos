import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import leases from '@/routes/properties/units/leases';
import type { Lease } from '@/types';

const DUE_DAY_OPTIONS = [
    { value: '1', label: '1st' },
    { value: '5', label: '5th' },
    { value: '10', label: '10th' },
    { value: '15', label: '15th' },
    { value: '20', label: '20th' },
    { value: '25', label: '25th' },
    { value: '31', label: 'Last day of month' },
];

export default function LeaseEditSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [dueDay, setDueDay] = useState(() =>
        lease ? String(lease.rent_due_day) : '1',
    );

    const noDeposit = Number.parseFloat(lease?.deposit_amount ?? '0') === 0;

    if (!lease || !lease.unit) {
        return null;
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

    return (
        <Sheet key={lease.id} open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Edit Lease</SheetTitle>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={leases.update.url({
                            property: lease.unit.property!.slug,
                            unit: lease.unit.slug,
                            lease: lease.id,
                        })}
                        method="put"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Occupancy
                                    </h3>
                                    <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                                        <div>
                                            <p className="mb-1 text-xs text-muted-foreground">
                                                Tenants
                                            </p>
                                            <div className="space-y-1">
                                                {(lease.tenants ?? []).length >
                                                0
                                                    ? lease.tenants.map((t) => (
                                                          <div
                                                              key={t.id}
                                                              className="flex items-center justify-between text-sm"
                                                          >
                                                              <span className="font-medium">
                                                                  {t.name}
                                                              </span>
                                                              {t.pivot
                                                                  ?.is_primary && (
                                                                  <span className="text-[10px] font-medium text-blue-600 uppercase">
                                                                      Primary
                                                                  </span>
                                                              )}
                                                          </div>
                                                      ))
                                                    : lease.primary_tenant && (
                                                          <div className="flex items-center justify-between text-sm">
                                                              <span className="font-medium">
                                                                  {
                                                                      lease
                                                                          .primary_tenant
                                                                          .name
                                                                  }
                                                              </span>
                                                              <span className="text-[10px] font-medium text-blue-600 uppercase">
                                                                  Primary
                                                              </span>
                                                          </div>
                                                      )}
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Unit
                                            </span>
                                            <span className="font-medium">
                                                {lease.unit?.name}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Property
                                            </span>
                                            <span className="font-medium">
                                                {lease.unit?.property?.name ??
                                                    '—'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Start date
                                            </span>
                                            <span className="tabular-nums">
                                                {formatDate(lease.start_date)}
                                            </span>
                                        </div>
                                    </div>
                                </section>

                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Rent & Terms
                                    </h3>

                                    <div className="flex items-start gap-4">
                                        <div className="min-w-0 flex-1">
                                            <Label htmlFor="rent_amount">
                                                Rent Amount (IDR)
                                            </Label>
                                            <Input
                                                id="rent_amount"
                                                name="rent_amount"
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    lease.rent_amount ?? ''
                                                }
                                                placeholder="Rent amount"
                                            />
                                            <InputError
                                                message={errors.rent_amount}
                                            />
                                        </div>

                                        <div className="shrink-0">
                                            <Label htmlFor="rent_due_day">
                                                Rent Due Every Month
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="rent_due_day"
                                                value={dueDay}
                                            />
                                            <Select
                                                value={dueDay}
                                                onValueChange={setDueDay}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {DUE_DAY_OPTIONS.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt.value}
                                                                value={
                                                                    opt.value
                                                                }
                                                            >
                                                                {opt.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.rent_due_day}
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Deposit
                                    </h3>
                                    <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Amount
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {Number.parseFloat(
                                                    lease.deposit_amount,
                                                ).toLocaleString('id-ID', {
                                                    style: 'currency',
                                                    currency: 'IDR',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })}
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
                                    </div>

                                    <div className="mt-4 grid gap-2">
                                        <Label htmlFor="deposit_refunded_at">
                                            Deposit Refunded At
                                        </Label>
                                        <Input
                                            id="deposit_refunded_at"
                                            name="deposit_refunded_at"
                                            type="date"
                                            defaultValue={
                                                lease.deposit_refunded_at?.split(
                                                    'T',
                                                )[0] ?? ''
                                            }
                                            disabled={noDeposit}
                                        />
                                        {noDeposit && (
                                            <p className="text-xs text-muted-foreground">
                                                No deposit was collected for
                                                this lease.
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.deposit_refunded_at}
                                        />
                                    </div>
                                </section>

                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </h3>
                                    <div className="grid gap-2">
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            defaultValue={lease.notes ?? ''}
                                            placeholder="Additional notes"
                                            className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                </section>

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={() => onOpenChange(false)}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>Save</Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
