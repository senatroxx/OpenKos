import { Head, useForm, usePage } from '@inertiajs/react';
import { Ellipsis, Pencil, Plus, RotateCcw, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import { InputError } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BILLING_UNITS } from '@/lib/constants';
import { formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { supportsPropertyPricing } from '@/lib/property-rental-mode';
import properties from '@/routes/properties';
import type { Property, PropertyRate } from '@/types';
import { PropertyLayout } from './layout';

type PropertyRatesFormData = {
    rates: PropertyRate[];
    updated_at: string | null;
};

type NewRateData = {
    amount: string;
    currency: string;
    billing_interval: number;
    billing_unit: PropertyRate['billing_unit'];
    is_active: boolean;
};

const billingUnitLabels: Record<PropertyRate['billing_unit'], string> = {
    day: 'Daily',
    week: 'Weekly',
    month: 'Monthly',
    year: 'Yearly',
};

function formatRatePeriod(
    interval: number,
    unit: PropertyRate['billing_unit'],
): string {
    if (interval === 1) {
        return t(billingUnitLabels[unit]);
    }

    return `${t('Every')} ${interval} ${t(`${unit}s`)}`;
}

export default function PropertyPricing({
    property,
    rates,
    defaultRate,
}: {
    property: Property;
    rates: PropertyRate[];
    defaultRate?: PropertyRate | null;
}) {
    const { setting } = usePage<{
        setting: { currency: string; supported_currencies: string[] };
    }>().props;
    const defaultCurrency = setting.currency.toUpperCase();
    const supportedCurrencies = setting.supported_currencies.includes(
        defaultCurrency,
    )
        ? setting.supported_currencies
        : [defaultCurrency, ...setting.supported_currencies];
    const [currencyFilter, setCurrencyFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('active');
    const [editingRateId, setEditingRateId] = useState<number | null>(null);
    const [editingAmount, setEditingAmount] = useState('');
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [currentDefaultRate, setCurrentDefaultRate] = useState(defaultRate);
    const [newRate, setNewRate] = useState<NewRateData>({
        amount: '',
        currency: defaultCurrency,
        billing_interval: 1,
        billing_unit: 'month',
        is_active: true,
    });
    const form = useForm<PropertyRatesFormData>({
        rates,
        updated_at: property.updated_at ?? null,
    });
    const currencies = Array.from(
        new Set([
            ...supportedCurrencies,
            ...form.data.rates.map((rate) => rate.currency.toUpperCase()),
        ]),
    ).sort();
    const visibleRates = form.data.rates.filter((rate) => {
        const currency = rate.currency.toUpperCase();
        const isActive = rate.is_active !== false;

        return (
            (currencyFilter === 'all' || currency === currencyFilter) &&
            (statusFilter === 'all' ||
                (statusFilter === 'active' && isActive) ||
                (statusFilter === 'inactive' && !isActive))
        );
    });
    const rentalMode = property.rental_mode ?? 'unit';
    const canManage = supportsPropertyPricing(property.rental_mode);

    function resetNewRate() {
        setNewRate({
            amount: '',
            currency: defaultCurrency,
            billing_interval: 1,
            billing_unit: 'month',
            is_active: true,
        });
    }

    function submitRates(nextRates: PropertyRate[], onSuccess?: () => void) {
        if (form.processing) {
            return;
        }

        form.transform((data) => ({ ...data, rates: nextRates }));
        form.put(properties.pricing.update.url(property), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                form.transform((data) => data);

                const pageRates = page.props.rates as
                    | PropertyRate[]
                    | undefined;
                const pageDefaultRate = page.props.defaultRate as
                    | PropertyRate
                    | null
                    | undefined;

                if (pageRates) {
                    form.setData((data) => ({
                        ...data,
                        rates: pageRates,
                        updated_at:
                            (page.props.property as Property | undefined)
                                ?.updated_at ?? data.updated_at,
                    }));
                }

                setCurrentDefaultRate(pageDefaultRate ?? null);
                onSuccess?.();
            },
            onError: () => form.transform((data) => data),
        });
    }

    function editRate(rate: PropertyRate) {
        if (rate.id == null) {
            return;
        }

        setEditingRateId(rate.id);
        setEditingAmount(rate.amount);
    }

    function cancelEdit() {
        setEditingRateId(null);
        setEditingAmount('');
    }

    function saveEdit(rate: PropertyRate) {
        if (rate.id == null) {
            return;
        }

        submitRates(
            form.data.rates.map((item) =>
                item.id === rate.id ? { ...item, amount: editingAmount } : item,
            ),
            cancelEdit,
        );
    }

    function toggleRate(rate: PropertyRate) {
        if (rate.id == null) {
            return;
        }

        submitRates(
            form.data.rates.map((item) =>
                item.id === rate.id
                    ? { ...item, is_active: item.is_active === false }
                    : item,
            ),
        );
    }

    function handleAddRate(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const submitter = (event.nativeEvent as SubmitEvent)
            .submitter as HTMLButtonElement | null;
        const keepDialogOpen = submitter?.dataset.keepDialogOpen === 'true';

        submitRates([...form.data.rates, newRate], () => {
            if (!keepDialogOpen) {
                setAddDialogOpen(false);
            }

            resetNewRate();
        });
    }

    function handleAddDialogChange(open: boolean) {
        setAddDialogOpen(open);

        if (!open) {
            resetNewRate();
        }
    }

    function renderRateRow(rate: PropertyRate) {
        const rateIndex = form.data.rates.findIndex(
            (item) => item.id === rate.id,
        );
        const isEditing = editingRateId === rate.id;
        const currency = rate.currency.toUpperCase();

        return (
            <Fragment key={rate.id}>
                <tr className="border-b last:border-b-0">
                    <td className="px-4 py-3 text-sm font-semibold text-muted-foreground">
                        {currency}
                    </td>
                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                        {formatPrice(rate.amount, currency)}
                    </td>
                    <td className="px-4 py-3 text-sm text-muted-foreground">
                        {formatRatePeriod(
                            rate.billing_interval,
                            rate.billing_unit,
                        )}
                    </td>
                    <td className="px-4 py-3">
                        <Badge
                            variant="outline"
                            className={
                                rate.is_active === false
                                    ? 'text-muted-foreground'
                                    : 'border-surface-green-border/80 bg-surface-green/70 text-surface-green-foreground'
                            }
                        >
                            {t(
                                rate.is_active === false
                                    ? 'Inactive'
                                    : 'Active',
                            )}
                        </Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    disabled={form.processing}
                                    aria-label={`${t('Actions for')} ${currency} ${t('rate')}`}
                                >
                                    <Ellipsis />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onSelect={() => editRate(rate)}
                                >
                                    <Pencil />
                                    {t('Edit amount')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => toggleRate(rate)}
                                >
                                    {rate.is_active === false ? (
                                        <RotateCcw />
                                    ) : (
                                        <X />
                                    )}
                                    {rate.is_active === false
                                        ? t('Reactivate')
                                        : t('Deactivate')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </td>
                </tr>
                {isEditing && (
                    <tr className="border-b bg-muted/20 last:border-b-0">
                        <td colSpan={5} className="px-4 py-4">
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div className="grid w-full max-w-md gap-2">
                                    <Label htmlFor={`rate-${rate.id}-amount`}>
                                        {t('Amount')}
                                    </Label>
                                    <Input
                                        id={`rate-${rate.id}-amount`}
                                        type="number"
                                        min={0}
                                        step="any"
                                        inputMode="decimal"
                                        value={editingAmount}
                                        onChange={(event) =>
                                            setEditingAmount(event.target.value)
                                        }
                                        autoFocus
                                    />
                                    <InputError
                                        message={
                                            form.errors[
                                                `rates.${rateIndex}.amount`
                                            ]
                                        }
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={cancelEdit}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={() => saveEdit(rate)}
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? t('Saving...')
                                            : t('Save')}
                                    </Button>
                                </div>
                            </div>
                        </td>
                    </tr>
                )}
            </Fragment>
        );
    }

    return (
        <PropertyLayout property={property} activeTab="pricing">
            <Head title={`${t('Pricing')} - ${property.name}`} />

            <div className="w-full max-w-6xl space-y-6">
                {!canManage ? (
                    <section className="rounded-lg border bg-card p-5">
                        <h2 className="text-lg font-semibold">
                            {t('Property pricing is not applicable')}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t(
                                'This property rents individual units. Manage pricing from the Units workspace.',
                            )}
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    {t('Entire property rates')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {rentalMode === 'hybrid'
                                        ? t(
                                              'Manage rates for renting the entire property. Unit pricing remains separate.',
                                          )
                                        : t(
                                              'Manage the rates customers will see when renting the entire property.',
                                          )}
                                </p>
                            </div>
                            <Button
                                type="button"
                                onClick={() => setAddDialogOpen(true)}
                            >
                                <Plus />
                                {t('Add Rate')}
                            </Button>
                        </div>

                        <section className="rounded-lg border bg-card p-5">
                            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {t('Default rate')}
                            </p>
                            {currentDefaultRate ? (
                                <div className="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span className="text-2xl font-semibold tabular-nums">
                                        {formatPrice(
                                            currentDefaultRate.amount,
                                            currentDefaultRate.currency,
                                        )}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {formatRatePeriod(
                                            currentDefaultRate.billing_interval,
                                            currentDefaultRate.billing_unit,
                                        )}
                                    </span>
                                </div>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {t('No active entire-property rate yet.')}
                                </p>
                            )}
                            <p className="mt-2 text-xs text-muted-foreground">
                                {t(
                                    'The default is selected by preferred currency and billing period order.',
                                )}
                            </p>
                        </section>

                        <div className="flex flex-wrap gap-3">
                            <Select
                                value={currencyFilter}
                                onValueChange={setCurrencyFilter}
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue
                                        placeholder={t('All currencies')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('All currencies')}
                                    </SelectItem>
                                    {currencies.map((currency) => (
                                        <SelectItem
                                            key={currency}
                                            value={currency}
                                        >
                                            {currency}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={statusFilter}
                                onValueChange={setStatusFilter}
                            >
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">
                                        {t('Active')}
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        {t('Inactive')}
                                    </SelectItem>
                                    <SelectItem value="all">
                                        {t('All statuses')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="overflow-x-auto rounded-lg border bg-card">
                            <table className="w-full min-w-[42rem] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Currency')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('Amount')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Billing period')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Status')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            <span className="sr-only">
                                                {t('Actions')}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleRates.length > 0 ? (
                                        visibleRates.map(renderRateRow)
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-10 text-center text-muted-foreground"
                                            >
                                                {form.data.rates.length === 0
                                                    ? t(
                                                          'No entire-property rates configured yet.',
                                                      )
                                                    : t(
                                                          'No rates match these filters.',
                                                      )}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <InputError message={form.errors.rates} />
                        <InputError message={form.errors.updated_at} />
                    </>
                )}
            </div>

            <Dialog open={addDialogOpen} onOpenChange={handleAddDialogChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Add Entire Property Rate')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Add a currency and billing-period variant for this property.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleAddRate} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="new-property-rate-currency">
                                {t('Currency')}
                            </Label>
                            <Select
                                value={newRate.currency}
                                onValueChange={(currency) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        currency,
                                    }))
                                }
                            >
                                <SelectTrigger id="new-property-rate-currency">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {supportedCurrencies.map((currency) => (
                                        <SelectItem
                                            key={currency}
                                            value={currency}
                                        >
                                            {currency}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="new-property-rate-amount">
                                {t('Amount')}
                            </Label>
                            <Input
                                id="new-property-rate-amount"
                                type="number"
                                min={0}
                                step="any"
                                inputMode="decimal"
                                value={newRate.amount}
                                onChange={(event) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        amount: event.target.value,
                                    }))
                                }
                                autoFocus
                            />
                            <InputError
                                message={
                                    form.errors[
                                        `rates.${form.data.rates.length}.amount`
                                    ]
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('Billing period')}</Label>
                            <div className="grid grid-cols-[5rem_minmax(0,1fr)] gap-2">
                                <Input
                                    aria-label={t('Billing interval')}
                                    type="number"
                                    min={1}
                                    value={newRate.billing_interval}
                                    onChange={(event) =>
                                        setNewRate((rate) => ({
                                            ...rate,
                                            billing_interval:
                                                Number.parseInt(
                                                    event.target.value,
                                                ) || 1,
                                        }))
                                    }
                                />
                                <Select
                                    value={newRate.billing_unit}
                                    onValueChange={(value) =>
                                        setNewRate((rate) => ({
                                            ...rate,
                                            billing_unit:
                                                value as PropertyRate['billing_unit'],
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        aria-label={t('Billing unit')}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BILLING_UNITS.map((unitName) => (
                                            <SelectItem
                                                key={unitName}
                                                value={unitName}
                                            >
                                                {unitName}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="new-property-rate-active"
                                checked={newRate.is_active}
                                onCheckedChange={(checked) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        is_active: checked === true,
                                    }))
                                }
                            />
                            <Label htmlFor="new-property-rate-active">
                                {t('Active')}
                            </Label>
                        </div>

                        <InputError message={form.errors.rates} />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleAddDialogChange(false)}
                                disabled={form.processing}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                variant="outline"
                                data-keep-dialog-open="true"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? t('Adding...')
                                    : t('Add & Add Another')}
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? t('Adding...')
                                    : t('Add Rate')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PropertyLayout>
    );
}
