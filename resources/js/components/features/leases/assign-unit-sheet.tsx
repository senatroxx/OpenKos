import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
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
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { DUE_DAY_OPTIONS } from '@/lib/constants';
import { computeMonthlyEquivalent, formatPrice } from '@/lib/formatters';
import tenants from '@/routes/tenants';
import type { AvailableUnit, UnitRate, Tenant } from '@/types';

type AvailableUnits = AvailableUnit[];

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
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(
        null,
    );
    const [selectedUnitId, setSelectedUnitId] = useState<number | null>(null);
    const [dueDay, setDueDay] = useState('1');
    const [hasDeposit, setHasDeposit] = useState(false);
    const dueDayInitialized = useRef(false);

    const selectedUnit =
        availableUnits.find((r) => r.id === selectedUnitId) ?? null;
    const rates = selectedUnit?.active_rates ?? [];

    const [selectedRateId, setSelectedRateId] = useState<number | null>(null);
    const [rentAmount, setRentAmount] = useState('');
    const [billingInterval, setBillingInterval] = useState('1');
    const [billingUnit, setBillingUnit] = useState('month');
    const [isCustom, setIsCustom] = useState(false);
    const [prevUnitId, setPrevUnitId] = useState(selectedUnitId);

    // Reset the rate/rent fields to the newly selected unit's default rate.
    if (selectedUnitId !== prevUnitId) {
        setPrevUnitId(selectedUnitId);

        const defaultRate = selectedUnit?.active_rates?.[0] ?? null;

        setSelectedRateId(defaultRate?.id ?? null);
        setRentAmount(defaultRate?.amount ?? '');
        setBillingInterval(String(defaultRate?.billing_interval ?? 1));
        setBillingUnit(defaultRate?.billing_unit ?? 'month');
        setIsCustom(false);
    }

    const selectedRate = rates.find((r) => r.id === selectedRateId) ?? null;

    const monthlyEquivalent = useMemo(
        () =>
            computeMonthlyEquivalent(
                rentAmount,
                Number.parseInt(billingInterval) || 1,
                billingUnit,
            ),
        [rentAmount, billingInterval, billingUnit],
    );

    function handleRateSelect(rate: UnitRate) {
        setSelectedRateId(rate.id ?? null);
        setRentAmount(rate.amount);
        setBillingInterval(String(rate.billing_interval));
        setBillingUnit(rate.billing_unit);
        setIsCustom(false);
    }

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

    function handlePropertyChange(val: number | string | null) {
        setSelectedPropertyId(val as number | null);
        setSelectedUnitId(null);
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
                setDueDay(match.value);
            }
        }

        dueDayInitialized.current = true;
    }

    return (
        <Sheet
            key={tenant?.id ?? 'closed'}
            open={open}
            onOpenChange={onOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Assign to Unit</SheetTitle>
                    <SheetDescription>
                        Assign {tenant?.name ?? 'tenant'} to a unit
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={
                            tenants.assignUnit.post({ tenant: tenant!.id }).url
                        }
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <input
                                    type="hidden"
                                    name="tenant_ids[]"
                                    value={tenant!.id}
                                />

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
                                            options={propertyOptions.map(
                                                (p) => ({
                                                    value: p.propertyId,
                                                    label: p.label,
                                                }),
                                            )}
                                            value={selectedPropertyId}
                                            onChange={handlePropertyChange}
                                            placeholder="Select property..."
                                            searchPlaceholder="Search property..."
                                            emptyText="No properties with available units."
                                        />
                                    </div>

                                    <div className="mt-3 grid gap-2">
                                        <Label>Unit</Label>
                                        <input
                                            type="hidden"
                                            name="unit_id"
                                            value={selectedUnitId ?? ''}
                                        />
                                        <SearchableSelect
                                            key={selectedPropertyId ?? 'none'}
                                            options={unitOptions}
                                            value={selectedUnitId}
                                            onChange={(val) =>
                                                setSelectedUnitId(
                                                    val as number | null,
                                                )
                                            }
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

                                    {selectedUnitId &&
                                        (rates.length > 0 ? (
                                            <div className="mt-4 grid gap-2">
                                                <Label>Unit Rate Options</Label>
                                                <div className="space-y-1">
                                                    {rates.map((rate) => (
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
                                                                    {formatPrice(
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
                                                No pricing configured for this
                                                unit. Enter a custom rent below
                                                or set up unit rates first.
                                            </p>
                                        ))}

                                    <div className="mt-4 grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="rent_amount">
                                                Rent Amount (IDR)
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="unit_rate_id"
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
                                                    setIsCustom(
                                                        Boolean(
                                                            selectedRate &&
                                                            Number.parseFloat(
                                                                e.target.value,
                                                            ) !==
                                                                Number.parseFloat(
                                                                    selectedRate.amount,
                                                                ),
                                                        ),
                                                    );
                                                }}
                                                placeholder="Select a unit first"
                                            />
                                            {isCustom && (
                                                <p className="text-xs font-medium text-amber-600">
                                                    Custom Price ⚠️ This lease
                                                    differs from unit default
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
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
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
                                        Assign to Unit
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
