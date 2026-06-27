import { Form } from '@inertiajs/react';
import { useState } from 'react';
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
    const [depositHandling, setDepositHandling] = useState('carry_forward');
    const [confirmedOutstanding, setConfirmedOutstanding] = useState(false);

    if (!lease) {
        return null;
    }

    const isOverdue = lease.status === 'active' && lease.payment_status === 'overdue';

    function handleClose() {
        onOpenChange(false);
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Renew Lease</SheetTitle>
                    <SheetDescription>
                        {lease.primary_tenant?.name ?? 'Tenant'} · {lease.room?.name}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={leases.renew.post({ lease: lease.id }).url}
                        method="post"
                        onSuccess={handleClose}
                    >
                        {({ processing, errors }) => (
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
                                        defaultValue={lease.rent_amount ? String(Number.parseInt(lease.rent_amount)) : ''}
                                        required
                                    />
                                    {errors.rent_amount && (
                                        <p className="text-sm text-red-500">{errors.rent_amount}</p>
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
                                            placeholder="e.g. 12"
                                            required
                                        />
                                        {errors.extension_value && (
                                            <p className="text-sm text-red-500">{errors.extension_value}</p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="extension_unit">Period</Label>
                                        <Select name="extension_unit" defaultValue="months">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="months">Months</SelectItem>
                                                <SelectItem value="years">Years</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.extension_unit && (
                                            <p className="text-sm text-red-500">{errors.extension_unit}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="deposit_handling">
                                        Security Deposit
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="deposit_handling"
                                        value={depositHandling}
                                    />
                                    <Select
                                        value={depositHandling}
                                        onValueChange={setDepositHandling}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DEPOSIT_HANDLING.map((d) => (
                                                <SelectItem key={d.value} value={d.value}>
                                                    {d.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.deposit_handling && (
                                        <p className="text-sm text-red-500">{errors.deposit_handling}</p>
                                    )}
                                </div>

                                {isOverdue && (
                                    <div className="space-y-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm">
                                        <p className="font-medium text-amber-800">
                                            Outstanding Balance
                                        </p>
                                        <p className="text-amber-700">
                                            This lease has overdue payments. Renewal will proceed
                                            with the existing balance.
                                        </p>
                                        <label className="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                checked={confirmedOutstanding}
                                                onChange={(e) => {
                                                    setConfirmedOutstanding(e.target.checked);
                                                }}
                                                className="mt-0.5 size-4"
                                            />
                                            <input
                                                type="hidden"
                                                name="confirmed_outstanding"
                                                value={confirmedOutstanding ? '1' : '0'}
                                            />
                                            <span className="text-amber-800">
                                                I confirm I want to renew despite the outstanding
                                                balance
                                            </span>
                                        </label>
                                    </div>
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
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
