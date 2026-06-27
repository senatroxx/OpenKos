import { router } from '@inertiajs/react';
import {  useRef, useState } from 'react';
import type {FormEvent} from 'react';
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
import leases from '@/routes/leases';
import type { Lease } from '@/types';

const MONTHS = [
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({ length: 5 }, (_, i) => String(CURRENT_YEAR - 2 + i));

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'transfer', label: 'Bank Transfer' },
    { value: 'ewallet', label: 'E-Wallet' },
    { value: 'other', label: 'Other' },
];

export default function RecordPaymentSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
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

        if (!lease) {
            return;
        }

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
                        <form
                            ref={formRef}
                            onSubmit={handleSubmit}
                            className="space-y-6 pt-4"
                        >
                            <section>
                                <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Billing Period
                                </h3>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="period_month">
                                            Month
                                        </Label>
                                        <Select
                                            name="period_month"
                                            defaultValue={String(
                                                now.getMonth() + 1,
                                            )}
                                        >
                                            <SelectTrigger id="period_month">
                                                <SelectValue placeholder="Select month" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {MONTHS.map((m) => (
                                                    <SelectItem
                                                        key={m.value}
                                                        value={m.value}
                                                    >
                                                        {m.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.period_month}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="period_year">
                                            Year
                                        </Label>
                                        <Select
                                            name="period_year"
                                            defaultValue={String(
                                                now.getFullYear(),
                                            )}
                                        >
                                            <SelectTrigger id="period_year">
                                                <SelectValue placeholder="Select year" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {YEARS.map((y) => (
                                                    <SelectItem key={y} value={y}>
                                                        {y}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.period_year}
                                        />
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Payment Details
                                </h3>

                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="amount">
                                            Amount (IDR)
                                        </Label>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            min={1}
                                            defaultValue={
                                                lease?.rent_amount ?? ''
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.amount}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_method">
                                            Payment Method
                                        </Label>
                                        <Select
                                            name="payment_method"
                                            defaultValue="cash"
                                        >
                                            <SelectTrigger id="payment_method">
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
                                        <InputError
                                            message={errors.payment_method}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="paid_at">
                                            Paid At
                                        </Label>
                                        <Input
                                            id="paid_at"
                                            name="paid_at"
                                            type="date"
                                            defaultValue={
                                                now
                                                    .toISOString()
                                                    .split('T')[0]
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.paid_at}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            Notes
                                        </Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            placeholder="Optional notes"
                                            className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        />
                                        <InputError
                                            message={errors.notes}
                                        />
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
                                                const file =
                                                    e.target.files?.[0];
                                                setFileName(
                                                    file?.name ?? null,
                                                );
                                            }}
                                        />
                                        {fileName && (
                                            <p className="text-xs text-muted-foreground">
                                                {fileName}
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.proof}
                                        />
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
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Recording...'
                                        : 'Record Payment'}
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
