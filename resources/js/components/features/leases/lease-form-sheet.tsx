import { router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/shared';
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
import { DUE_DAY_OPTIONS } from '@/lib/constants';
import { BILLING_STRATEGIES } from '@/lib/constants/billing';
import { computeMonthlyEquivalent, formatPrice } from '@/lib/formatters';
import properties from '@/routes/properties';
import type { Property, Unit, UnitRate } from '@/types';

export default function LeaseFormSheet({
    unit,
    property,
    open,
    onOpenChange,
}: {
    unit?: Unit | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { tenants } = usePage<{
        tenants: { id: number; name: string; phone: string }[];
    }>().props;
    const defaultRate = unit?.active_rates?.[0] ?? null;
    const { data, setData, processing, errors, reset } = useForm({
        tenant_ids: [] as number[],
        start_date: new Date().toISOString().split('T')[0],
        unit_rate_id: (defaultRate?.id ?? null) as number | null,
        rent_amount: defaultRate?.amount ?? '',
        billing_interval: String(defaultRate?.billing_interval ?? 1),
        billing_unit: (defaultRate?.billing_unit ?? 'month') as string,
        billing_strategy: 'advance',
        rent_due_day: '1',
        deposit_amount: '0',
        deposit_paid_at: '',
        notes: '',
    });
    const dueDayInitialized = useRef(false);
    const [hasDeposit, setHasDeposit] = useState(false);
    const capacity = unit?.capacity ?? 1;

    const selectedRate =
        unit?.active_rates?.find((r) => r.id === data.unit_rate_id) ?? null;

    const isCustom = selectedRate !== null
        && Number.parseFloat(data.rent_amount) !== Number.parseFloat(selectedRate.amount);

    const monthlyEquivalent = useMemo(() => {
        return computeMonthlyEquivalent(
            data.rent_amount,
            Number.parseInt(data.billing_interval) || 1,
            data.billing_unit,
        );
    }, [data.rent_amount, data.billing_interval, data.billing_unit]);

    function handleOpenChange(open: boolean) {
        if (!open) {
            reset();
            dueDayInitialized.current = false;
        }

        onOpenChange(open);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        router.post(properties.units.leases.store.url({
            property: property.slug,
            unit: unit!.slug,
        }), data, {
            onSuccess: () => onOpenChange(false),
        });
    }

    function handleRateSelect(rate: UnitRate) {
        setData({
            ...data,
            unit_rate_id: rate.id ?? null,
            rent_amount: rate.amount,
            billing_interval: String(rate.billing_interval),
            billing_unit: rate.billing_unit,
        });
    }

    function handleStartDateChange(e: React.ChangeEvent<HTMLInputElement>) {
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
                setData('rent_due_day', match.value);
            }
        }

        dueDayInitialized.current = true;
    }

    function toggleTenant(tenantId: number) {
        setData('tenant_ids', data.tenant_ids.includes(tenantId)
            ? data.tenant_ids.filter((id) => id !== tenantId)
            : [...data.tenant_ids, tenantId]);
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        Assign Tenant{capacity > 1 ? 's' : ''}
                    </SheetTitle>
                    <SheetDescription>
                        Assign {capacity > 1 ? 'tenants' : 'a tenant'} to{' '}
                        {unit?.name ?? 'this unit'}
                        {capacity > 1 && ` (capacity: ${capacity})`}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit} className="space-y-6 pt-4">
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

                                <div className="max-h-48 overflow-y-auto rounded-md border">
                                    {(tenants ?? []).map((t) => {
                                        const isSelected =
                                            data.tenant_ids.includes(t.id);
                                        const atCapacity =
                                            !isSelected &&
                                            data.tenant_ids.length >= capacity;

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
                                                        toggleTenant(t.id)
                                                    }
                                                    disabled={atCapacity}
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
                                    {data.tenant_ids.length === 0
                                        ? 'Select at least one tenant'
                                        : `${data.tenant_ids.length} of ${capacity} selected`}
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
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => {
                                        handleStartDateChange(e);
                                        setData('start_date', e.target.value);
                                    }}
                                />
                                <InputError
                                    message={errors.start_date}
                                />
                            </div>

                            {unit?.active_rates &&
                            unit.active_rates.length > 0 ? (
                                <div className="mt-4 grid gap-2">
                                    <Label>Unit Rate Options</Label>
                                    <div className="space-y-1">
                                        {unit.active_rates.map(
                                            (rate) => (
                                                <label
                                                    key={`${rate.billing_interval}-${rate.billing_unit}`}
                                                    className={`flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition-colors ${
                                                        data.unit_rate_id ===
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
                                                            data.unit_rate_id ===
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
                                                            {rate.billing_interval}{' '}
                                                            {rate.billing_unit}
                                                            {rate.billing_interval > 1
                                                                ? 's'
                                                                : ''}
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {formatPrice(
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
                                    No pricing configured for this unit.
                                    Please set up unit rates first.
                                </p>
                            )}

                            <div className="mt-4 grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Rent Amount (IDR)</Label>
                                    <Input
                                        id="rent_amount"
                                        type="number"
                                        min={0}
                                        value={data.rent_amount}
                                        onChange={(e) => {
                                            setData('rent_amount', e.target.value);
                                        }}
                                    />
                                    {isCustom && (
                                        <p className="text-xs font-medium text-amber-600">
                                            Custom Price ⚠️ This lease
                                            differs from unit default
                                            pricing.
                                        </p>
                                    )}
                                    <div className="min-h-[1rem]">
                                        {monthlyEquivalent && (
                                            <p className="text-xs text-muted-foreground">
                                                {monthlyEquivalent}
                                            </p>
                                        )}
                                    </div>
                                    <InputError
                                        message={errors.rent_amount}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rent_due_day">
                                        Rent Due Every Month
                                    </Label>
                                    <Select
                                        value={data.rent_due_day}
                                        onValueChange={(v) => setData('rent_due_day', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select due day" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DUE_DAY_OPTIONS.map(
                                                (opt) => (
                                                    <SelectItem
                                                        key={opt.value}
                                                        value={opt.value}
                                                    >
                                                        {opt.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[1rem]" />
                                    <InputError
                                        message={errors.rent_due_day}
                                    />
                                </div>
                            </div>

                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="billing_strategy">
                                    Billing Strategy
                                </Label>
                                <Select
                                    value={data.billing_strategy}
                                    onValueChange={(v) => setData('billing_strategy', v)}
                                >
                                    <SelectTrigger id="billing_strategy">
                                        <SelectValue placeholder="Select billing strategy" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BILLING_STRATEGIES.map(
                                            (opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.billing_strategy}
                                />
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
                                                type="number"
                                                min={0}
                                                value={data.deposit_amount}
                                                onChange={(e) => setData('deposit_amount', e.target.value)}
                                            />
                                            <InputError
                                                message={errors.deposit_amount}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="deposit_paid_at">
                                                Paid Date
                                            </Label>
                                            <Input
                                                id="deposit_paid_at"
                                                type="date"
                                                value={data.deposit_paid_at}
                                                onChange={(e) => setData('deposit_paid_at', e.target.value)}
                                            />
                                            <InputError
                                                message={errors.deposit_paid_at}
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
                                placeholder="Additional notes"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
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
                                {data.tenant_ids.length > 1 ? 's' : ''}
                            </Button>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
