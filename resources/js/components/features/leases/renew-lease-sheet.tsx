import { useForm } from '@inertiajs/react';
import { renew } from '@/actions/App/Http/Controllers/LeaseController';
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
import { DEPOSIT_HANDLING_OPTIONS } from '@/lib/constants/lease';
import type { Lease } from '@/types';

export default function RenewLeaseSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, processing, errors, submit } = useForm({
        rent_amount: lease?.rent_amount ? String(Number.parseInt(lease.rent_amount)) : '',
        extension_value: '',
        extension_unit: 'months',
        deposit_handling: 'refund',
        confirmed_outstanding: false,
    });

    if (!lease) {
        return null;
    }

    const isOverdue =
        lease.status === 'active' && lease.payment_status === 'overdue';

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(renew(lease!.id), {
            onSuccess: handleClose,
        });
    }

    function handleClose() {
        onOpenChange(false);
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Renew Lease</SheetTitle>
                    <SheetDescription>
                        {lease.primary_tenant?.name ?? 'Tenant'} ·{' '}
                        {lease.unit?.name}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit}>
                            <div className="space-y-6 pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="rent_amount">
                                        New Rent Amount (IDR)
                                    </Label>
                                    <Input
                                        id="rent_amount"
                                        name="rent_amount"
                                        type="number"
                                        min={1}
                                        value={data.rent_amount}
                                        onChange={(e) => setData('rent_amount', e.target.value)}
                                        required
                                    />
                                    {errors.rent_amount && (
                                        <p className="text-sm text-red-500">
                                            {errors.rent_amount}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="extension_value">
                                            Extension
                                        </Label>
                                        <Input
                                            id="extension_value"
                                            name="extension_value"
                                            type="number"
                                            min={1}
                                            max={120}
                                            value={data.extension_value}
                                            onChange={(e) => setData('extension_value', e.target.value)}
                                            placeholder="e.g. 12"
                                            required
                                        />
                                        {errors.extension_value && (
                                            <p className="text-sm text-red-500">
                                                {errors.extension_value}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="extension_unit">
                                            Period
                                        </Label>
                                        <Select
                                            name="extension_unit"
                                            value={data.extension_unit}
                                            onValueChange={(v) => setData('extension_unit', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="months">
                                                    Months
                                                </SelectItem>
                                                <SelectItem value="years">
                                                    Years
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.extension_unit && (
                                            <p className="text-sm text-red-500">
                                                {errors.extension_unit}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="deposit_handling">
                                        Security Deposit
                                    </Label>
                                    <Select
                                        value={data.deposit_handling}
                                        onValueChange={(v) => setData('deposit_handling', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DEPOSIT_HANDLING_OPTIONS.map((d) => (
                                                <SelectItem
                                                    key={d.value}
                                                    value={d.value}
                                                >
                                                    {d.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.deposit_handling && (
                                        <p className="text-sm text-red-500">
                                            {errors.deposit_handling}
                                        </p>
                                    )}
                                </div>

                                {isOverdue && (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Outstanding Balance
                                        </AlertTitle>
                                        <AlertDescription>
                                            <p className="mb-3">
                                                This lease has overdue payments.
                                                Renewal will proceed with the
                                                existing balance.
                                            </p>
                                            <label className="flex items-start gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        data.confirmed_outstanding
                                                    }
                                                    onChange={(e) => {
                                                        setData('confirmed_outstanding', e.target.checked);
                                                    }}
                                                    className="mt-0.5 size-4"
                                                />
                                                <span>
                                                    I confirm I want to renew
                                                    despite the outstanding
                                                    balance
                                                </span>
                                            </label>
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={handleClose}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Renew Lease
                                    </Button>
                                </div>
                            </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
