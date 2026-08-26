import { useForm, usePage } from '@inertiajs/react';
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
import properties from '@/routes/properties';
import type { Property, Unit, UnitRate } from '@/types';
import { UnitLayout } from './layout';

type UnitFormData = {
    name: string;
    floor: string;
    capacity: string;
    rates: UnitRate[];
    size_sqm: string;
    status: string;
    description: string;
    notes: string;
    updated_at: string | null;
};

type NewRateData = {
    amount: string;
    currency: string;
    billing_interval: number;
    billing_unit: UnitRate['billing_unit'];
    is_active: boolean;
};

const billingUnitLabels: Record<UnitRate['billing_unit'], string> = {
    day: 'Daily',
    week: 'Weekly',
    month: 'Monthly',
    year: 'Yearly',
};

function formatRatePeriod(
    interval: number,
    unit: UnitRate['billing_unit'],
): string {
    if (interval === 1) {
        return t(billingUnitLabels[unit]);
    }

    return `${t('Every')} ${interval} ${t(`${unit}s`)}`;
}

export default function UnitRates({
    property,
    unit,
}: {
    property: Property;
    unit: Unit;
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
    const [newRate, setNewRate] = useState<NewRateData>({
        amount: '',
        currency: defaultCurrency,
        billing_interval: 1,
        billing_unit: 'month',
        is_active: true,
    });
    const { data, setData, transform, submit, processing, errors } =
        useForm<UnitFormData>({
            name: unit.name,
            floor: unit.floor ?? '',
            capacity: String(unit.capacity ?? 1),
            rates: unit.rates ?? unit.active_rates ?? [],
            size_sqm: unit.size_sqm ?? '',
            status: unit.status ?? 'available',
            description: unit.description ?? '',
            notes: unit.notes ?? '',
            updated_at: unit.updated_at ?? null,
        });
    const currencies = Array.from(
        new Set([
            ...supportedCurrencies,
            ...data.rates.map((rate) =>
                (rate.currency ?? defaultCurrency).toUpperCase(),
            ),
        ]),
    ).sort();

    const visibleRates = data.rates.filter((rate) => {
        const currency = (rate.currency ?? defaultCurrency).toUpperCase();
        const isActive = rate.is_active !== false;

        return (
            (currencyFilter === 'all' || currency === currencyFilter) &&
            (statusFilter === 'all' ||
                (statusFilter === 'active' && isActive) ||
                (statusFilter === 'inactive' && !isActive))
        );
    });

    function resetNewRate() {
        setNewRate({
            amount: '',
            currency: defaultCurrency,
            billing_interval: 1,
            billing_unit: 'month',
            is_active: true,
        });
    }

    function submitRates(rates: UnitRate[], onSuccess?: () => void) {
        if (processing) {
            return;
        }

        transform((formData) => ({ ...formData, rates }));
        submit(
            properties.units.update({
                property: property.slug,
                unit: unit.slug,
            }),
            {
                preserveState: true,
                onSuccess: (page) => {
                    transform((formData) => formData);

                    const updatedUnit = page.props.unit as Unit | undefined;

                    setData((current) => ({
                        ...current,
                        rates: updatedUnit?.rates ?? current.rates,
                        updated_at:
                            updatedUnit?.updated_at ?? current.updated_at,
                    }));

                    onSuccess?.();
                },
                onError: () => transform((formData) => formData),
            },
        );
    }

    function editRate(rate: UnitRate) {
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

    function saveEdit(rate: UnitRate) {
        if (rate.id == null) {
            return;
        }

        const rates = data.rates.map((item) =>
            item.id === rate.id ? { ...item, amount: editingAmount } : item,
        );

        submitRates(rates, cancelEdit);
    }

    function toggleRate(rate: UnitRate) {
        if (rate.id == null) {
            return;
        }

        submitRates(
            data.rates.map((item) =>
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

        submitRates([...data.rates, newRate], () => {
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

    function renderRateRow(rate: UnitRate) {
        const rateIndex = data.rates.findIndex((item) => item.id === rate.id);
        const isEditing = editingRateId === rate.id;
        const currency = (rate.currency ?? defaultCurrency).toUpperCase();

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
                                    disabled={processing}
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
                                            errors[`rates.${rateIndex}.amount`]
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
                                        disabled={processing}
                                    >
                                        {processing
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
        <UnitLayout property={property} unit={unit} activeTab="rates">
            <div className="w-full space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('Pricing Rates')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                "Compare and manage this unit's pricing variants.",
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

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={currencyFilter}
                        onValueChange={setCurrencyFilter}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder={t('All currencies')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {t('All currencies')}
                            </SelectItem>
                            {currencies.map((currency) => (
                                <SelectItem key={currency} value={currency}>
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
                                        {t('No rates match these filters.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <InputError message={errors.rates} />
                <InputError message={errors.updated_at} />
            </div>

            <Dialog open={addDialogOpen} onOpenChange={handleAddDialogChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Add Pricing Rate')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Add a currency and billing-period variant for this unit.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleAddRate} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="new-rate-currency">
                                {t('Currency')}
                            </Label>
                            <Select
                                value={newRate.currency}
                                onValueChange={(value) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        currency: value,
                                    }))
                                }
                            >
                                <SelectTrigger id="new-rate-currency">
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
                            <InputError
                                message={
                                    errors[
                                        `rates.${data.rates.length}.currency`
                                    ]
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="new-rate-amount">
                                {t('Amount')}
                            </Label>
                            <Input
                                id="new-rate-amount"
                                type="number"
                                min={0}
                                step="any"
                                inputMode="decimal"
                                required
                                value={newRate.amount}
                                onChange={(event) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        amount: event.target.value,
                                    }))
                                }
                                placeholder={t('e.g. 1500000')}
                            />
                            <InputError
                                message={
                                    errors[`rates.${data.rates.length}.amount`]
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
                                                value as UnitRate['billing_unit'],
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
                            <InputError
                                message={
                                    errors[
                                        `rates.${data.rates.length}.billing_interval`
                                    ] ??
                                    errors[
                                        `rates.${data.rates.length}.billing_unit`
                                    ]
                                }
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="new-rate-active"
                                checked={newRate.is_active}
                                onCheckedChange={(checked) =>
                                    setNewRate((rate) => ({
                                        ...rate,
                                        is_active: checked === true,
                                    }))
                                }
                            />
                            <Label htmlFor="new-rate-active">
                                {t('Active')}
                            </Label>
                        </div>

                        <InputError message={errors.rates} />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleAddDialogChange(false)}
                                disabled={processing}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                variant="outline"
                                data-keep-dialog-open="true"
                                disabled={processing}
                            >
                                {processing
                                    ? t('Adding...')
                                    : t('Add & Add Another')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('Adding...') : t('Add Rate')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </UnitLayout>
    );
}
