import { Form, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import properties from '@/routes/properties';

type RoomRate = {
    id: number;
    billing_interval: number;
    billing_unit: 'day' | 'week' | 'month' | 'year';
    amount: string;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
    active_rates: RoomRate[];
    capacity?: number;
};

type Property = {
    id: number;
    name: string;
};

const DUE_DAY_OPTIONS = [
    { value: '1', label: '1st' },
    { value: '5', label: '5th' },
    { value: '10', label: '10th' },
    { value: '15', label: '15th' },
    { value: '20', label: '20th' },
    { value: '25', label: '25th' },
    { value: '31', label: 'Last day of month' },
];

function formatCurrency(value: string | number): string {
    const num = typeof value === 'string' ? Number.parseFloat(value) : value;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function computeMonthlyEquivalent(
    amount: string | null,
    interval: number | null,
    unit: string | null,
): string {
    if (!amount || !interval || !unit) {
        return '';
    }

    const num = Number.parseFloat(amount);

    if (isNaN(num)) {
        return '';
    }

    const int = interval || 1;
    let monthly: number;

    switch (unit) {
        case 'day':
            monthly = (num * 365) / 12 / int;
            break;
        case 'week':
            monthly = (num * 52) / 12 / int;
            break;
        case 'month':
            monthly = num / int;
            break;
        case 'year':
            monthly = (num * 12) / int;
            break;
        default:
            return '';
    }

    return `≈ ${formatCurrency(Math.round(monthly))}/month`;
}

export default function LeaseFormSheet({
    room,
    property,
    open,
    onOpenChange,
}: {
    room?: Room | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { tenants } = usePage<{
        tenants: { id: number; name: string; phone: string }[];
    }>().props;
    const [selectedTenantIds, setSelectedTenantIds] = useState<number[]>([]);
    const [dueDay, setDueDay] = useState('1');
    const [hasDeposit, setHasDeposit] = useState(false);
    const dueDayInitialized = useRef(false);

    const defaultRate = room?.active_rates?.[0] ?? null;

    const [selectedRateId, setSelectedRateId] = useState<number | null>(
        () => defaultRate?.id ?? null,
    );
    const [rentAmount, setRentAmount] = useState(
        () => defaultRate?.amount ?? '',
    );
    const [billingInterval, setBillingInterval] = useState(
        () => String(defaultRate?.billing_interval ?? 1),
    );
    const [billingUnit, setBillingUnit] = useState(
        () => defaultRate?.billing_unit ?? 'month',
    );
    const [isCustom, setIsCustom] = useState(false);

    const capacity = room?.capacity ?? 1;
    const selectedRate =
        room?.active_rates?.find((r) => r.id === selectedRateId) ?? null;

    const monthlyEquivalent = useMemo(() => {
        return computeMonthlyEquivalent(
            rentAmount,
            Number.parseInt(billingInterval) || 1,
            billingUnit,
        );
    }, [rentAmount, billingInterval, billingUnit]);

    const handleRateSelect = useCallback((rate: RoomRate) => {
        setSelectedRateId(rate.id);
        setRentAmount(rate.amount);
        setBillingInterval(String(rate.billing_interval));
        setBillingUnit(rate.billing_unit);
        setIsCustom(false);
    }, []);

    const handleStartDateChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            if (dueDayInitialized.current) {
                return;
            }

            const day = e.target.value
                ? parseInt(e.target.value.split('-')[2], 10)
                : NaN;

            if (!isNaN(day) && day >= 1 && day <= 31) {
                const match = DUE_DAY_OPTIONS.find(
                    (o) => parseInt(o.value, 10) === day,
                );

                if (match) {
                    setDueDay(match.value);
                }
            }

            dueDayInitialized.current = true;
        },
        [],
    );

    function toggleTenant(tenantId: number) {
        setSelectedTenantIds((prev) =>
            prev.includes(tenantId)
                ? prev.filter((id) => id !== tenantId)
                : [...prev, tenantId],
        );
    }

    return (
        <Sheet key="lease-form" open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Assign Tenant{capacity > 1 ? 's' : ''}</SheetTitle>
                    <SheetDescription>
                        Assign {capacity > 1 ? 'tenants' : 'a tenant'} to{' '}
                        {room?.name ?? 'this room'}
                        {capacity > 1 && ` (capacity: ${capacity})`}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={properties.rooms.leases.store.url({
                            property: property.id,
                            room: room!.id,
                        })}
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Section 1 — Who
                                    </h3>

                                    <div className="grid gap-2">
                                        <Label>
                                            Tenants{' '}
                                            {capacity > 1 &&
                                                `(select up to ${capacity})`}
                                        </Label>

                                        {selectedTenantIds.map((id) => (
                                            <input
                                                key={id}
                                                type="hidden"
                                                name="tenant_ids[]"
                                                value={id}
                                            />
                                        ))}

                                        <div className="max-h-48 overflow-y-auto rounded-md border">
                                            {(tenants ?? []).map((t) => {
                                                const isSelected =
                                                    selectedTenantIds.includes(
                                                        t.id,
                                                    );
                                                const atCapacity =
                                                    !isSelected &&
                                                    selectedTenantIds.length >=
                                                        capacity;

                                                return (
                                                    <label
                                                        key={t.id}
                                                        className={`flex cursor-pointer items-center gap-3 border-b px-3 py-2 text-sm last:border-0 hover:bg-muted/50 ${
                                                            isSelected
                                                                ? 'bg-blue-50 dark:bg-blue-950'
                                                                : ''
                                                        } ${
                                                            atCapacity
                                                                ? 'cursor-not-allowed opacity-50'
                                                                : ''
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={() =>
                                                                toggleTenant(
                                                                    t.id,
                                                                )
                                                            }
                                                            disabled={
                                                                atCapacity
                                                            }
                                                            className="size-4"
                                                        />
                                                        <span className="font-medium">
                                                            {t.name}
                                                        </span>
                                                        {t.phone && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {t.phone}
                                                            </span>
                                                        )}
                                                    </label>
                                                );
                                            })}
                                        </div>

                                        <p className="text-xs text-muted-foreground">
                                            {selectedTenantIds.length === 0
                                                ? 'Select at least one tenant'
                                                : `${selectedTenantIds.length} of ${capacity} selected`}
                                        </p>

                                        <InputError
                                            message={errors.tenant_ids}
                                        />
                                    </div>
                                </section>

                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Section 2 — Stay
                                    </h3>

                                    <div className="grid gap-2">
                                        <Label htmlFor="start_date">
                                            Move-in Date
                                        </Label>
                                        <Input
                                            id="start_date"
                                            name="start_date"
                                            type="date"
                                            defaultValue={
                                                new Date()
                                                    .toISOString()
                                                    .split('T')[0]
                                            }
                                            onChange={handleStartDateChange}
                                            required
                                        />
                                        <InputError
                                            message={errors.start_date}
                                        />
                                    </div>

                                    {room?.active_rates &&
                                    room.active_rates.length > 0 ? (
                                        <div className="mt-4 grid gap-2">
                                            <Label>Room Rate Options</Label>
                                            <div className="space-y-1">
                                                {room.active_rates.map(
                                                    (rate) => (
                                                        <label
                                                            key={`${rate.billing_interval}-${rate.billing_unit}`}
                                                            className={`flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition-colors ${
                                                                selectedRateId ===
                                                                rate.id
                                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                                                                    : 'hover:bg-muted/50'
                                                            }`}
                                                        >
                                                            <input
                                                                type="radio"
                                                                name="rate_option"
                                                                value={rate.id}
                                                                checked={
                                                                    selectedRateId ===
                                                                    rate.id
                                                                }
                                                                onChange={() =>
                                                                    handleRateSelect(
                                                                        rate,
                                                                    )
                                                                }
                                                                className="size-4 accent-blue-600"
                                                            />
                                                            <div className="flex flex-1 items-center justify-between">
                                                                <span>
                                                                    {
                                                                        rate.billing_interval
                                                                    }{' '}
                                                                    {
                                                                        rate.billing_unit
                                                                    }
                                                                    {rate.billing_interval >
                                                                    1
                                                                        ? 's'
                                                                        : ''}
                                                                </span>
                                                                <span className="font-medium tabular-nums">
                                                                    {formatCurrency(
                                                                        rate.amount,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="mt-4 text-xs text-amber-600">
                                            No pricing configured for this room.
                                            Please set up room rates first.
                                        </p>
                                    )}

                                    <div className="mt-4 grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label>Rent Amount (IDR)</Label>
                                            <input
                                                type="hidden"
                                                name="room_rate_id"
                                                value={selectedRateId ?? ''}
                                            />
                                            <input
                                                type="hidden"
                                                name="billing_interval"
                                                value={billingInterval}
                                            />
                                            <input
                                                type="hidden"
                                                name="billing_unit"
                                                value={billingUnit}
                                            />
                                            <Input
                                                id="rent_amount"
                                                name="rent_amount"
                                                type="number"
                                                min={0}
                                                value={rentAmount}
                                                onChange={(e) => {
                                                    setRentAmount(
                                                        e.target.value,
                                                    );

                                                    if (
                                                        selectedRate &&
                                                        Number.parseFloat(
                                                            e.target.value,
                                                        ) !==
                                                            Number.parseFloat(
                                                                selectedRate.amount,
                                                            )
                                                    ) {
                                                        setIsCustom(true);
                                                    } else {
                                                        setIsCustom(false);
                                                    }
                                                }}
                                            />
                                            {isCustom && (
                                                <p className="text-xs font-medium text-amber-600">
                                                    Custom Price ⚠️ This lease
                                                    differs from room default
                                                    pricing.
                                                </p>
                                            )}
                                            {monthlyEquivalent && (
                                                <p className="text-xs text-muted-foreground">
                                                    {monthlyEquivalent}
                                                </p>
                                            )}
                                            <InputError
                                                message={errors.rent_amount}
                                            />
                                        </div>
                                        <div className="grid gap-2">
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
                                                    <SelectValue placeholder="Select due day" />
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
                                    <Collapsible
                                        open={hasDeposit}
                                        onOpenChange={setHasDeposit}
                                        className="space-y-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                Section 3 — Deposit
                                            </h3>
                                            <CollapsibleTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    type="button"
                                                    className="flex items-center gap-2 text-xs text-muted-foreground"
                                                >
                                                    {hasDeposit
                                                        ? 'Has deposit'
                                                        : 'No deposit'}
                                                    <ChevronDown
                                                        className={`size-3 transition-transform ${hasDeposit ? 'rotate-180' : ''}`}
                                                    />
                                                </Button>
                                            </CollapsibleTrigger>
                                        </div>

                                        <CollapsibleContent className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="deposit_amount">
                                                        Deposit Amount (IDR)
                                                    </Label>
                                                    <Input
                                                        id="deposit_amount"
                                                        name="deposit_amount"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={0}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.deposit_amount
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="deposit_paid_at">
                                                        Paid Date
                                                    </Label>
                                                    <Input
                                                        id="deposit_paid_at"
                                                        name="deposit_paid_at"
                                                        type="date"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.deposit_paid_at
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </section>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Additional notes"
                                        className="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.notes} />
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
                                        Assign Tenant
                                        {selectedTenantIds.length > 1
                                            ? 's'
                                            : ''}
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
