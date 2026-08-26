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
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PAYMENT_METHODS } from '@/lib/constants/billing';
import { formatDate, formatPrice, todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { store } from '@/routes/portal/billing';
import type { Invoice } from '@/types';

export default function SubmitPortalPaymentForm({
    invoice,
    onSuccess,
    onCancel,
}: {
    invoice: Invoice;
    onSuccess?: () => void;
    onCancel?: () => void;
}) {
    const payableAmount = invoice.payable_amount ?? invoice.outstanding ?? '0';
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [fileName, setFileName] = useState<string | null>(null);
    const formRef = useRef<HTMLFormElement>(null);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const formData = new FormData(event.currentTarget);
        formData.append('invoice_id', String(invoice.id));

        setProcessing(true);
        setErrors({});

        router.post(store.url(), formData, {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                formRef.current?.reset();
                setFileName(null);
                onSuccess?.();
            },
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <form
            ref={formRef}
            onSubmit={handleSubmit}
            className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
        >
            <div className="grid gap-4">
                <div
                    className="grid gap-1 border-y py-4 text-sm"
                    aria-live="polite"
                >
                    <p className="font-medium">
                        {t('Outstanding balance')}:{' '}
                        <span className="tabular-nums">
                            {formatPrice(payableAmount, invoice.currency)}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        {t('Due')} {formatDate(invoice.due_date)}.{' '}
                        {t('Your payment will be submitted for verification.')}
                    </p>
                </div>

                <InputError message={errors.invoice_id} />

                <div className="grid gap-2">
                    <Label htmlFor="amount">
                        {t('Amount')} ({invoice.currency})
                    </Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        min={0}
                        max={payableAmount}
                        step="any"
                        inputMode="decimal"
                        defaultValue={payableAmount}
                        aria-describedby="amount-help"
                        required
                    />
                    <p
                        id="amount-help"
                        className="text-sm text-muted-foreground"
                    >
                        {t('Enter up to')}{' '}
                        {formatPrice(payableAmount, invoice.currency)}.
                    </p>
                    <InputError message={errors.amount} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="payment_method">
                            {t('Payment Method')}
                        </Label>
                        <Select name="payment_method" defaultValue="transfer">
                            <SelectTrigger
                                id="payment_method"
                                className="w-full"
                            >
                                <SelectValue placeholder={t('Select method')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {PAYMENT_METHODS.map((method) => (
                                        <SelectItem
                                            key={method.value}
                                            value={method.value}
                                        >
                                            {method.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.payment_method} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="paid_at">{t('Paid At')}</Label>
                        <Input
                            id="paid_at"
                            name="paid_at"
                            type="date"
                            defaultValue={todayISO()}
                            required
                        />
                        <InputError message={errors.paid_at} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes">{t('Notes')}</Label>
                    <Textarea
                        id="notes"
                        name="notes"
                        placeholder={t('Optional notes')}
                    />
                    <InputError message={errors.notes} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="proof">
                        {t('Payment Proof (optional)')}
                    </Label>
                    <Input
                        id="proof"
                        name="proof"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf"
                        aria-describedby="proof-help"
                        className="file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                        onChange={(event) =>
                            setFileName(event.target.files?.[0]?.name ?? null)
                        }
                    />
                    <p
                        id="proof-help"
                        className="text-sm text-muted-foreground"
                    >
                        {t('JPG, PNG, or PDF up to 10 MB.')}
                    </p>
                    {fileName && (
                        <p className="truncate text-xs text-muted-foreground">
                            {t('Selected:')} {fileName}
                        </p>
                    )}
                    <InputError message={errors.proof} />
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-4">
                {onCancel && (
                    <Button
                        variant="outline"
                        type="button"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        {t('Cancel')}
                    </Button>
                )}
                <Button disabled={processing}>
                    {processing ? t('Submitting...') : t('Submit Payment')}
                </Button>
            </div>
        </form>
    );
}
