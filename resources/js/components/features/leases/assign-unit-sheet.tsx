import { useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { InputError, SearchableSelect } from '@/components/shared';
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
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import tenants from '@/routes/tenants';
import type { AvailableUnit, UnitRate, Tenant } from '@/types';

type AvailableUnits = AvailableUnit[];

const BILLING_STRATEGIES = [
    { value: 'advance', label: 'Advance (due within period)' },
    { value: 'arrears', label: 'Arrears (due after period)' },
];

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
            monthly = num / 12 / int;
            break;
        default:
            return '';
    }

    return `≈ ${formatCurrency(Math.round(monthly))}/month`;
}

export default function AssignUnitSheet({
    tenant,
    availableUnits,
    open,
    onOpenChange,
}: {
    tenant?: Tenant | null;
    availableUnits: AvailableUnits;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        unit_id: null as number | null,
        tenant_ids: [] as number[],
        start_date: new Date().toISOString().split('T')[0],
        unit_rate_id: null as number | null,
        rent_amount: '',
        billing_interval: '1',
        billing_unit: 'month' as string,
        billing_strategy: 'advance',
        rent_due_day: '1',
        deposit_amount: '0',
        deposit_paid_at: '',
        notes: '',
    });
    const dueDayInitialized = useRef(false);
    const [hasDeposit, setHasDeposit] = useState(false);
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(
        null,
    );

    const selectedUnit =
        availableUnits.find((r) => r.id === data.unit_id) ?? null;
    const rates = selectedUnit?.active_rates ?? [];
    const selectedRate = rates.find((r) => r.id === data.unit_rate_id) ?? null;

    const isCustom =
        selectedRate !== null &&
        Number.parseFloat(data.rent_amount) !==
            Number.parseFloat(selectedRate.amount);

    const monthlyEquivalent = useMemo(() => {
        return computeMonthlyEquivalent(
            data.rent_amount,
            Number.parseInt(data.billing_interval) || 1,
            data.billing_unit,
        );
    }, [data.rent_amount, data.billing_interval, data.billing_unit]);

    const propertyOptions = useMemo(() => {
        const seen = new Set<number>();

        return availableUnits
            .filter((r) => {
                if (seen.has(r.property_id)) {
                    return false;
                }

                seen.add(r.property_id);

                return true;
            })
            .map((r) => ({
                propertyId: r.property_id,
                label: `${r.property?.name ?? 'Unknown'} — ${r.property?.city?.name ?? ''}`,
            }));
    }, [availableUnits]);

    const filteredUnits = useMemo(
        () =>
            selectedPropertyId
                ? availableUnits.filter(
                      (r) => r.property_id === selectedPropertyId,
                  )
                : [],
        [availableUnits, selectedPropertyId],
    );

    const unitOptions = filteredUnits.map((r) => {
        const spotsLeft = r.capacity - r.occupied_count;
        const suffix =
            r.occupied_count > 0
                ? ` (${r.occupied_count}/${r.capacity} occupied, ${spotsLeft} spot${spotsLeft === 1 ? '' : 's'} left)`
                : r.capacity > 1
                  ? ` (capacity ${r.capacity})`
                  : '';

        return {
            value: r.id,
            label: `${r.name}${suffix}`,
        };
    });

    function handleOpenChange(open: boolean) {
        if (!open) {
            reset();
            setSelectedPropertyId(null);
            dueDayInitialized.current = false;
        }

        onOpenChange(open);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(tenants.assignUnit.post({ tenant: tenant!.id }).url, {
            onSuccess: () => onOpenChange(false),
        });
    }

    function handleUnitSelect(val: number | string | null) {
        const unitId = val as number | null;
        const unit = availableUnits.find((r) => r.id === unitId) ?? null;
        const rate = unit?.active_rates?.[0] ?? null;
        setData({
            unit_id: unitId,
            unit_rate_id: rate?.id ?? null,
            rent_amount: rate?.amount ?? '',
            billing_interval: String(rate?.billing_interval ?? 1),
            billing_unit: rate?.billing_unit ?? 'month',
        });
    }

    function handlePropertyChange(val: number | string | null) {
        setSelectedPropertyId(val as number | null);
        setData('unit_id', null);
    }

    function handleRateSelect(rate: UnitRate) {
        setData({
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

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Assign to Unit</SheetTitle>
                    <SheetDescription>
                        Assign {tenant?.name ?? 'tenant'} to a unit
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit} className="space-y-6 pt-4">
                        <section>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Section 1 — Who
                            </h3>

                            <div className="mb-3 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                                {tenant?.name ?? 'Unknown tenant'}
                            </div>

                            <div className="grid gap-2">
                                <Label>Property</Label>
                                <SearchableSelect
                                    options={propertyOptions.map((p) => ({
                                        value: p.propertyId,
                                        label: p.label,
                                    }))}
                                    value={selectedPropertyId}
                                    onChange={handlePropertyChange}
                                    placeholder="Select property..."
                                    searchPlaceholder="Search property..."
                                    emptyText="No properties with available units."
                                />
                            </div>

                            <div className="mt-3 grid gap-2">
                                <Label>Unit</Label>
                                <SearchableSelect
                                    key={selectedPropertyId ?? 'none'}
                                    options={unitOptions}
                                    value={data.unit_id}
                                    onChange={handleUnitSelect}
                                    placeholder={
                                        selectedPropertyId
                                            ? 'Select unit...'
                                            : 'Choose a property first'
                                    }
                                    searchPlaceholder="Search unit..."
                                    emptyText="No available units in this property."
                                    disabled={!selectedPropertyId}
                                />
                                <InputError message={errors.unit_id} />
                            </div>
                        </section>

                        <section>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Section 2 — Stay
                            </h3>

                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Move-in Date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => {
                                        handleStartDateChange(e);
                                        setData('start_date', e.target.value);
                                    }}
                                />
                                <InputError message={errors.start_date} />
                            </div>

                            {data.unit_id &&
                                (rates.length > 0 ? (
                                    <div className="mt-4 grid gap-2">
                                        <Label>Unit Rate Options</Label>
                                        <div className="space-y-1">
                                            {rates.map((rate) => (
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
                                                            {
                                                                rate.billing_interval
                                                            }{' '}
                                                            {rate.billing_unit}
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
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-4 text-xs text-amber-600">
                                        No pricing configured for this unit.
                                        Enter a custom rent below or set up unit
                                        rates first.
                                    </p>
                                ))}

                            <div className="mt-4 grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="rent_amount">
                                        Rent Amount (IDR)
                                    </Label>
                                    <Input
                                        id="rent_amount"
                                        type="number"
                                        min={0}
                                        value={data.rent_amount}
                                        onChange={(e) => {
                                            setData(
                                                'rent_amount',
                                                e.target.value,
                                            );
                                        }}
                                        placeholder="Select a unit first"
                                    />
                                    {isCustom && (
                                        <p className="text-xs font-medium text-amber-600">
                                            Custom Price ⚠️ This lease differs
                                            from unit default pricing.
                                        </p>
                                    )}
                                    <div className="min-h-[1rem]">
                                        {monthlyEquivalent && (
                                            <p className="text-xs text-muted-foreground">
                                                {monthlyEquivalent}
                                            </p>
                                        )}
                                    </div>
                                    <InputError message={errors.rent_amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rent_due_day">
                                        Rent Due Every Month
                                    </Label>
                                    <Select
                                        value={data.rent_due_day}
                                        onValueChange={(v) =>
                                            setData('rent_due_day', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select due day" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DUE_DAY_OPTIONS.map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[1rem]" />
                                    <InputError message={errors.rent_due_day} />
                                </div>
                            </div>

                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="billing_strategy">
                                    Billing Strategy
                                </Label>
                                <Select
                                    value={data.billing_strategy}
                                    onValueChange={(v) =>
                                        setData('billing_strategy', v)
                                    }
                                >
                                    <SelectTrigger
                                        id="billing_strategy"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select billing strategy" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BILLING_STRATEGIES.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.billing_strategy} />
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
                                                onChange={(e) =>
                                                    setData(
                                                        'deposit_amount',
                                                        e.target.value,
                                                    )
                                                }
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
                                                onChange={(e) =>
                                                    setData(
                                                        'deposit_paid_at',
                                                        e.target.value,
                                                    )
                                                }
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
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError message={errors.notes} />
                        </div>
                        <SheetFooter>
                            <Button disabled={processing}>
                                Assign to Unit
                            </Button>
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => onOpenChange(false)}
                                disabled={processing}
                            >
                                Cancel
                            </Button>
                        </SheetFooter>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
