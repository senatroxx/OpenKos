import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
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
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { PAYABLE_STATUSES, PAYMENT_METHODS } from '@/lib/constants/billing';
import { formatPeriod, formatPrice } from '@/lib/formatters';
import leases from '@/routes/leases';
import type { Lease, RentScheduleEntry } from '@/types';

function RecordPaymentForm({
    lease,
    onOpenChange,
}: {
    lease: Lease;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [fileName, setFileName] = useState<string | null>(null);
    const [invoices, setInvoices] = useState<RentScheduleEntry[] | null>(null);
    const [selectedInvoiceId, setSelectedInvoiceId] = useState<string>('');
    const [fetchError, setFetchError] = useState(false);
    const formRef = useRef<HTMLFormElement>(null);
    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    useEffect(() => {
        const controller = new AbortController();

        fetch(`/leases/${lease.id}/rent-schedule`, {
            signal: controller.signal,
        })
            .then((r) => r.json())
            .then((d: { schedule: RentScheduleEntry[] }) => {
                const payable = d.schedule.filter((entry) =>
                    PAYABLE_STATUSES.includes(entry.status),
                );
                setInvoices(payable);

                if (payable.length > 0) {
                    setSelectedInvoiceId(String(payable[0].id));
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setFetchError(true);
                }
            });

        return () => controller.abort();
    }, [lease.id]);

    const selectedInvoice = invoices?.find(
        (entry) => String(entry.id) === selectedInvoiceId,
    );

    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();

        const formData = new FormData(e.currentTarget);

        setProcessing(true);
        setErrors({});

        router.post(leases.payments.store.url({ lease: lease.id }), formData, {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                onOpenChange(false);
                formRef.current?.reset();
                setFileName(null);
            },
            onError: (errs) => {
                setErrors(errs);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    }

    return (
        <form ref={formRef} onSubmit={handleSubmit} className="space-y-6 pt-4">
            <section>
                <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    Invoice
                </h3>

                <div className="grid gap-2">
                    <Label htmlFor="invoice_id">Billing Period</Label>
                    {invoices === null ? (
                        <p className="text-sm text-muted-foreground">
                            {fetchError
                                ? 'Failed to load invoices.'
                                : 'Loading invoices...'}
                        </p>
                    ) : invoices.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No payable invoices for this lease.
                        </p>
                    ) : (
                        <Select
                            name="invoice_id"
                            value={selectedInvoiceId}
                            onValueChange={setSelectedInvoiceId}
                        >
                            <SelectTrigger id="invoice_id">
                                <SelectValue placeholder="Select invoice" />
                            </SelectTrigger>
                            <SelectContent>
                                {invoices.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {formatPeriod(entry.period_start)}
                                        {' — '}
                                        {formatPrice(entry.outstanding)}
                                        {entry.status === 'partial' &&
                                            ' outstanding'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <InputError message={errors.invoice_id} />
                </div>
            </section>

            <section>
                <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    Payment Details
                </h3>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount (IDR)</Label>
                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            min={1}
                            key={selectedInvoiceId}
                            defaultValue={
                                selectedInvoice?.outstanding ??
                                lease?.rent_amount ??
                                ''
                            }
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="payment_method">Payment Method</Label>
                            <Select name="payment_method" defaultValue="cash">
                                <SelectTrigger id="payment_method" className="w-full">
                                    <SelectValue placeholder="Select method" />
                                </SelectTrigger>
                                <SelectContent>
                                    {PAYMENT_METHODS.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.payment_method} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="paid_at">Paid At</Label>
                            <Input
                                id="paid_at"
                                name="paid_at"
                                type="date"
                                defaultValue={todayStr}
                                required
                            />
                            <InputError message={errors.paid_at} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            placeholder="Optional notes"
                            className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="proof">Payment Proof (optional)</Label>
                        <Input
                            id="proof"
                            name="proof"
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            className="file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                setFileName(file?.name ?? null);
                            }}
                        />
                        {fileName && (
                            <p className="text-xs text-muted-foreground">
                                {fileName}
                            </p>
                        )}
                        <InputError message={errors.proof} />
                    </div>
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
                <Button
                    disabled={
                        processing || invoices === null || invoices.length === 0
                    }
                >
                    {processing ? 'Recording...' : 'Record Payment'}
                </Button>
            </div>
        </form>
    );
}

export default function RecordPaymentSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Record Payment</SheetTitle>
                    <SheetDescription>
                        Record a rent payment for this lease.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    {lease && (
                        <RecordPaymentForm
                            key={lease.id}
                            lease={lease}
                            onOpenChange={onOpenChange}
                        />
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
