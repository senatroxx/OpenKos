import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
import { PAYMENT_METHODS } from '@/lib/constants/billing';
import { formatDate, formatPrice } from '@/lib/formatters';
import leases from '@/routes/leases';
import type { NeedsAttentionInvoice } from '@/types';

export default function QueuePaymentSheet({
    invoice,
    open,
    onOpenChange,
}: {
    invoice: NeedsAttentionInvoice | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [fileName, setFileName] = useState<string | null>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const now = new Date();

    function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();

        if (!invoice) {
return;
}

        const formData = new FormData(e.currentTarget);
        formData.append('invoice_id', String(invoice.id));

        setProcessing(true);
        setErrors({});

        router.post(
            leases.payments.store.url({ lease: invoice.lease_id }),
            formData,
            {
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
            },
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Record Payment</SheetTitle>
                    <SheetDescription>
                        {invoice ? (
                            <>
                                {invoice.tenant_name} — {invoice.reference}
                                <br />
                                {formatPrice(invoice.total)} · Due{' '}
                                {formatDate(invoice.due_date)} ·{' '}
                                <span className="text-red-600">
                                    {formatPrice(invoice.outstanding)} outstanding
                                </span>
                            </>
                        ) : (
                            'Record a rent payment.'
                        )}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form
                        ref={formRef}
                        onSubmit={handleSubmit}
                        className="space-y-6 pt-4"
                    >
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (IDR)</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min={1}
                                    key={invoice?.id}
                                    defaultValue={invoice?.outstanding ?? ''}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_method">
                                        Payment Method
                                    </Label>
                                    <Select
                                        name="payment_method"
                                        defaultValue="cash"
                                    >
                                        <SelectTrigger id="payment_method" className="w-full">
                                            <SelectValue placeholder="Select method" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PAYMENT_METHODS.map((m) => (
                                                <SelectItem
                                                    key={m.value}
                                                    value={m.value}
                                                >
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
                                        defaultValue={
                                            now.toISOString().split('T')[0]
                                        }
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
                                <Label htmlFor="proof">
                                    Payment Proof (optional)
                                </Label>
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

                        <div className="flex items-center justify-end gap-4 pt-2">
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => onOpenChange(false)}
                                disabled={processing}
                            >
                                Cancel
                            </Button>
                            <Button disabled={processing}>
                                {processing ? 'Recording...' : 'Record Payment'}
                            </Button>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
