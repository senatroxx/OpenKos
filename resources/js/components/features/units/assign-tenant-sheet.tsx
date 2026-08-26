import { useForm, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { DUE_DAY_OPTIONS } from '@/lib/constants';
import { BILLING_STRATEGIES } from '@/lib/constants/billing';
import {
    computeMonthlyEquivalent,
    formatPrice,
    todayISO,
} from '@/lib/formatters';
import { t } from '@/lib/i18n';
import properties from '@/routes/properties';
import type { Property, Unit, UnitRate } from '@/types';

export default function AssignTenantSheet({
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
    const { tenants, setting } = usePage<{
        tenants: { id: number; name: string; phone: string }[];
        setting: { currency: string };
    }>().props;
    const [hasDeposit, setHasDeposit] = useState(false);
    const [overridePrice, setOverridePrice] = useState(false);
    const dueDayInitialized = useRef(false);

    const defaultRate =
        unit?.active_rates?.find(
            (rate) =>
                (rate.currency ?? setting.currency).toUpperCase() ===
                setting.currency.toUpperCase(),
        ) ??
        unit?.active_rates?.[0] ??
        null;
    const activeLease = unit?.leases?.[0] ?? null;

    const { data, setData, transform, submit, reset, processing, errors } =
        useForm({
            tenant_ids: [] as number[],
            start_date: activeLease?.start_date ?? todayISO(),
            unit_rate_id: activeLease ? null : (defaultRate?.id ?? null),
            rent_amount: activeLease
                ? (activeLease.rent_amount ?? '')
                : (defaultRate?.amount ?? ''),
            billing_interval: String(
                activeLease?.billing_interval ??
                    defaultRate?.billing_interval ??
                    1,
            ),
            billing_unit:
                activeLease?.billing_unit ??
                defaultRate?.billing_unit ??
                'month',
            rent_due_day: activeLease
                ? String(activeLease.rent_due_day ?? '')
                : '1',
            billing_strategy: activeLease
                ? (activeLease.billing_strategy ?? '')
                : 'advance',
            deposit_amount: '0',
            deposit_paid_at: '',
            notes: '',
        });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setHasDeposit(false);
            setOverridePrice(false);
            dueDayInitialized.current = false;
        }
    }

    const capacity = unit?.capacity ?? 1;
    const hasRates = (unit?.active_rates?.length ?? 0) > 0;
    const rates = useMemo(() => unit?.active_rates ?? [], [unit]);
    const [selectedCurrency, setSelectedCurrency] = useState(
        (
            activeLease?.currency ??
            defaultRate?.currency ??
            setting.currency
        ).toUpperCase(),
    );
    const availableCurrencies = useMemo(
        () =>
            Array.from(
                new Set(
                    rates.map((rate) =>
                        (rate.currency ?? setting.currency).toUpperCase(),
                    ),
                ),
            ).sort(),
        [rates, setting.currency],
    );
    const displayCurrency =
        selectedCurrency ||
        availableCurrencies[0] ||
        setting.currency.toUpperCase();
    const visibleRates = rates.filter(
        (rate) =>
            (rate.currency ?? setting.currency).toUpperCase() ===
            displayCurrency.toUpperCase(),
    );
    const selectedRate = rates.find((r) => r.id === data.unit_rate_id) ?? null;
    const currency =
        activeLease?.currency ?? selectedRate?.currency ?? displayCurrency;

    function handleOverrideToggle(checked: boolean) {
        setOverridePrice(checked);

        // Reverting the override restores the selected rate's price.
        if (!checked && selectedRate) {
            setData('rent_amount', selectedRate.amount);
        }
    }

    const monthlyEquivalent = useMemo(() => {
        return computeMonthlyEquivalent(
            data.rent_amount,
            Number.parseInt(data.billing_interval) || 1,
            data.billing_unit,
            currency,
        );
    }, [data.rent_amount, data.billing_interval, data.billing_unit, currency]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => {
            if (hasDeposit) {
                return d;
            }

            const payload: Record<string, unknown> = { ...d };
            delete payload.deposit_amount;
            delete payload.deposit_paid_at;

            return payload;
        });
        submit(
            properties.units.leases.store({
                property: property.slug,
                unit: unit!.slug,
            }),
            { onSuccess: () => handleOpenChange(false) },
        );
    }

    function handleRateSelect(rate: UnitRate) {
        setSelectedCurrency((rate.currency ?? setting.currency).toUpperCase());
        setData((prev) => ({
            ...prev,
            unit_rate_id: rate.id ?? null,
            rent_amount: rate.amount,
            billing_interval: String(rate.billing_interval),
            billing_unit: rate.billing_unit,
        }));
        setOverridePrice(false);
    }

    function handleStartDateChange(e: React.ChangeEvent<HTMLInputElement>) {
        setData('start_date', e.target.value);

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
        setData((prev) => ({
            ...prev,
            tenant_ids: prev.tenant_ids.includes(tenantId)
                ? prev.tenant_ids.filter((id) => id !== tenantId)
                : [...prev.tenant_ids, tenantId],
        }));
    }

    return (
        <Sheet
            key={unit?.id ?? 'closed'}
            open={open}
            onOpenChange={handleOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {t(capacity > 1 ? 'Assign Tenants' : 'Assign Tenant')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            capacity > 1
                                ? 'Assign tenants to this unit'
                                : 'Assign a tenant to this unit',
                        )}{' '}
                        {unit?.name ?? t('this unit')}
                        {capacity > 1 && ` (${t('capacity')}: ${capacity})`}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <section>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('Section 1 — Who')}
                            </h3>

                            <div className="grid gap-2">
                                <Label>
                                    {t('Tenants')}{' '}
                                    {capacity > 1 &&
                                        `(${t('select up to :count', { count: capacity })})`}
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
                                        ? t('Select at least one tenant')
                                        : t(':count of :total selected', {
                                              count: data.tenant_ids.length,
                                              total: capacity,
                                          })}
                                </p>

                                <InputError message={errors.tenant_ids} />
                            </div>
                        </section>

                        <section>
                            <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('Section 2 — Stay')}
                            </h3>

                            <div className="grid gap-2">
                                <Label htmlFor="start_date">
                                    {t('Move-in Date')}
                                </Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={handleStartDateChange}
                                    disabled={activeLease != null}
                                    required
                                />
                                <InputError message={errors.start_date} />
                            </div>

                            {activeLease ? (
                                <div className="mt-4 rounded-md border bg-muted/30 p-3 text-sm">
                                    <p className="font-medium">
                                        {t('Existing lease terms')}
                                    </p>
                                    <p className="mt-1 text-muted-foreground">
                                        {activeLease.rent_amount
                                            ? formatPrice(
                                                  activeLease.rent_amount,
                                                  activeLease.currency,
                                              )
                                            : t('Custom amount')}{' '}
                                        {activeLease.billing_label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Adding a tenant keeps this lease currency and billing terms.',
                                        )}
                                    </p>
                                </div>
                            ) : unit?.active_rates &&
                              unit.active_rates.length > 0 ? (
                                <div className="mt-4 grid gap-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <Label>{t('Unit Rate Options')}</Label>
                                        {availableCurrencies.length > 1 ? (
                                            <Select
                                                value={displayCurrency}
                                                onValueChange={(value) => {
                                                    const nextRate = rates.find(
                                                        (rate) =>
                                                            (
                                                                rate.currency ??
                                                                setting.currency
                                                            ).toUpperCase() ===
                                                            value,
                                                    );

                                                    setSelectedCurrency(value);

                                                    if (nextRate) {
                                                        handleRateSelect(
                                                            nextRate,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger className="w-24">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableCurrencies.map(
                                                        (availableCurrency) => (
                                                            <SelectItem
                                                                key={
                                                                    availableCurrency
                                                                }
                                                                value={
                                                                    availableCurrency
                                                                }
                                                            >
                                                                {
                                                                    availableCurrency
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <span className="text-sm font-medium text-muted-foreground">
                                                {displayCurrency}
                                            </span>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        {visibleRates.map((rate) => (
                                            <label
                                                key={rate.id}
                                                className={`flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition-colors ${
                                                    data.unit_rate_id ===
                                                    rate.id
                                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                                                        : 'hover:bg-muted/50'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    checked={
                                                        data.unit_rate_id ===
                                                        rate.id
                                                    }
                                                    onChange={() =>
                                                        handleRateSelect(rate)
                                                    }
                                                    className="size-4 accent-blue-600"
                                                />
                                                <div className="flex flex-1 items-center justify-between">
                                                    <span>
                                                        {rate.billing_interval}{' '}
                                                        {rate.billing_unit}
                                                        {rate.billing_interval >
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        {formatPrice(
                                                            rate.amount,
                                                            rate.currency,
                                                        )}
                                                    </span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <p className="mt-4 text-xs text-surface-amber-foreground">
                                    {t(
                                        'No pricing configured for this unit. Please set up unit rates first.',
                                    )}
                                </p>
                            )}

                            {!activeLease && (
                                <div className="mt-4 grid gap-2">
                                    <Label htmlFor="rent_due_day">
                                        {t('Rent Due Every Month')}
                                    </Label>
                                    <Select
                                        value={data.rent_due_day}
                                        onValueChange={(v) =>
                                            setData('rent_due_day', v)
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue
                                                placeholder={t(
                                                    'Select due day',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DUE_DAY_OPTIONS.map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {t(opt.label)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.rent_due_day} />
                                </div>
                            )}

                            {hasRates && !activeLease && (
                                <label className="mt-4 flex cursor-pointer items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={overridePrice}
                                        onChange={(e) =>
                                            handleOverrideToggle(
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4"
                                    />
                                    {t('Override room price?')}
                                </label>
                            )}

                            {(overridePrice || !hasRates) && !activeLease && (
                                <div className="mt-4 grid gap-2">
                                    <Label htmlFor="rent_amount">
                                        {t('Rent Amount')} ({currency})
                                    </Label>
                                    <Input
                                        id="rent_amount"
                                        type="number"
                                        min={0}
                                        value={data.rent_amount}
                                        onChange={(e) =>
                                            setData(
                                                'rent_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {monthlyEquivalent && (
                                        <p className="text-xs text-muted-foreground">
                                            {monthlyEquivalent}
                                        </p>
                                    )}
                                    <InputError message={errors.rent_amount} />
                                </div>
                            )}
                            {!activeLease && (
                                <div className="mt-4 grid gap-2">
                                    <Label htmlFor="billing_strategy">
                                        {t('Billing Strategy')}
                                    </Label>
                                    <Select
                                        value={data.billing_strategy}
                                        onValueChange={(v) =>
                                            setData('billing_strategy', v)
                                        }
                                    >
                                        <SelectTrigger id="billing_strategy">
                                            <SelectValue
                                                placeholder={t(
                                                    'Select billing strategy',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {BILLING_STRATEGIES.map((s) => (
                                                <SelectItem
                                                    key={s.value}
                                                    value={s.value}
                                                >
                                                    {t(s.label)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.billing_strategy}
                                    />
                                </div>
                            )}
                        </section>

                        {!activeLease && (
                            <section>
                                <Collapsible
                                    open={hasDeposit}
                                    onOpenChange={setHasDeposit}
                                    className="space-y-3"
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            {t('Section 3 — Deposit')}
                                        </h3>
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                className="flex items-center gap-2 text-xs text-muted-foreground"
                                            >
                                                {hasDeposit
                                                    ? t('Has deposit')
                                                    : t('No deposit')}
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
                                                    {t('Deposit Amount')} (
                                                    {currency})
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
                                                    message={
                                                        errors.deposit_amount
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="deposit_paid_at">
                                                    {t('Paid Date')}
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
                                                    message={
                                                        errors.deposit_paid_at
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </section>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('Notes')}</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder={t('Additional notes')}
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing}>
                            {t(
                                data.tenant_ids.length > 1
                                    ? 'Assign Tenants'
                                    : 'Assign Tenant',
                            )}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
