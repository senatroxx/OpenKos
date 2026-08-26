import { useForm, usePage } from '@inertiajs/react';
import { InputError } from '@/components/shared';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { t } from '@/lib/i18n';
import leases from '@/routes/leases';
import type { Lease } from '@/types';

const DEPOSIT_HANDLING = [
    { value: 'carry_forward', label: 'Carry Forward' },
    { value: 'refund_and_collect_new', label: 'Refund & Collect New' },
    { value: 'forfeit', label: 'Forfeit' },
];

export default function RenewLeaseSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { setting } = usePage<{ setting: { currency: string } }>().props;
    const currency = lease?.currency ?? setting.currency;
    const { data, setData, transform, submit, reset, processing, errors } =
        useForm({
            rent_amount: lease?.rent_amount ?? '',
            extension_value: '',
            extension_unit: 'months',
            deposit_handling: 'carry_forward',
            confirmed_outstanding: false,
        });

    if (!lease) {
        return null;
    }

    const isOverdue =
        lease.status === 'active' && lease.payment_status === 'overdue';

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleClose() {
        handleOpenChange(false);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => ({
            ...d,
            confirmed_outstanding: d.confirmed_outstanding ? '1' : '0',
        }));
        submit(leases.renew({ lease: lease!.id }), {
            onSuccess: handleClose,
        });
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{t('Renew Lease')}</SheetTitle>
                    <SheetDescription>
                        {lease.primary_tenant?.name ?? t('Tenant')} ·{' '}
                        {lease.unit?.name}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="rent_amount">
                                {t('New Rent Amount')} ({currency})
                            </Label>
                            <Input
                                id="rent_amount"
                                type="number"
                                min={0}
                                step="any"
                                inputMode="decimal"
                                value={data.rent_amount}
                                onChange={(e) =>
                                    setData('rent_amount', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.rent_amount} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="extension_value">
                                    {t('Extension')}
                                </Label>
                                <Input
                                    id="extension_value"
                                    type="number"
                                    min={1}
                                    max={120}
                                    placeholder={t('e.g. 12')}
                                    value={data.extension_value}
                                    onChange={(e) =>
                                        setData(
                                            'extension_value',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.extension_value} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="extension_unit">
                                    {t('Period')}
                                </Label>
                                <Select
                                    value={data.extension_unit}
                                    onValueChange={(v) =>
                                        setData('extension_unit', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="months">
                                            {t('Months')}
                                        </SelectItem>
                                        <SelectItem value="years">
                                            {t('Years')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.extension_unit} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deposit_handling">
                                {t('Security Deposit')}
                            </Label>
                            <Select
                                value={data.deposit_handling}
                                onValueChange={(v) =>
                                    setData('deposit_handling', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {DEPOSIT_HANDLING.map((d) => (
                                        <SelectItem
                                            key={d.value}
                                            value={d.value}
                                        >
                                            {t(d.label)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.deposit_handling} />
                        </div>

                        {isOverdue && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    {t('Outstanding Balance')}
                                </AlertTitle>
                                <AlertDescription>
                                    <p className="mb-3">
                                        {t(
                                            'This lease has overdue payments. Renewal will proceed with the existing balance.',
                                        )}
                                    </p>
                                    <label className="flex items-start gap-3">
                                        <input
                                            type="checkbox"
                                            checked={data.confirmed_outstanding}
                                            onChange={(e) =>
                                                setData(
                                                    'confirmed_outstanding',
                                                    e.target.checked,
                                                )
                                            }
                                            className="mt-0.5 size-4"
                                        />
                                        <span>
                                            {t(
                                                'I confirm I want to renew despite the outstanding balance',
                                            )}
                                        </span>
                                    </label>
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing}>
                            {t('Renew Lease')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
