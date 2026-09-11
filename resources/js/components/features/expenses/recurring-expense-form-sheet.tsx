import { useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { InputError, SearchableSelect } from '@/components/shared';
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
import { Textarea } from '@/components/ui/textarea';
import { BILLING_UNITS } from '@/lib/constants';
import { todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import recurringExpenses from '@/routes/expenses/recurring';
import type { ExpenseCategory, RecurringExpense } from '@/types';

type RecurringExpenseFormData = {
    property_id: string;
    expense_category_id: string;
    amount: string;
    currency: string;
    vendor: string;
    description: string;
    billing_interval: string;
    billing_unit: RecurringExpense['billing_unit'];
    start_date: string;
    end_date: string;
};

export default function RecurringExpenseFormSheet({
    recurringExpense,
    properties,
    categories,
    currencies,
    open,
    onOpenChange,
}: {
    recurringExpense?: RecurringExpense | null;
    properties: { id: number; name: string }[];
    categories: ExpenseCategory[];
    currencies: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { setting } = usePage<{
        setting: { currency: string };
    }>().props;
    const isEdit = Boolean(recurringExpense);
    const defaultCurrency = setting.currency.toUpperCase();
    const currencyOptions = useMemo(
        () =>
            Array.from(
                new Set([...currencies, defaultCurrency].map((currency) => currency.toUpperCase())),
            ).sort(),
        [currencies, defaultCurrency],
    );
    const propertyOptions = properties.map((property) => ({
        value: String(property.id),
        label: property.name,
    }));
    const availableCategories = categories.filter(
        (category) =>
            category.is_active || category.id === recurringExpense?.expense_category_id,
    );
    const { data, setData, submit, reset, processing, errors } =
        useForm<RecurringExpenseFormData>({
            property_id: recurringExpense?.property_id
                ? String(recurringExpense.property_id)
                : properties[0]
                  ? String(properties[0].id)
                  : '',
            expense_category_id: recurringExpense?.expense_category_id
                ? String(recurringExpense.expense_category_id)
                : (categories.find((category) => category.is_active)?.id.toString() ?? ''),
            amount: recurringExpense?.amount ?? '',
            currency: recurringExpense?.currency ?? defaultCurrency,
            vendor: recurringExpense?.vendor ?? '',
            description: recurringExpense?.description ?? '',
            billing_interval: String(recurringExpense?.billing_interval ?? 1),
            billing_unit: recurringExpense?.billing_unit ?? 'month',
            start_date: recurringExpense?.start_date ?? todayISO(),
            end_date: recurringExpense?.end_date ?? '',
        });

    function close(next: boolean): void {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(event: React.FormEvent): void {
        event.preventDefault();

        submit(
            isEdit
                ? recurringExpenses.update(recurringExpense!)
                : recurringExpenses.store(),
            { onSuccess: () => close(false) },
        );
    }

    return (
        <Sheet key={recurringExpense?.id ?? 'new'} open={open} onOpenChange={close}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {t(isEdit ? 'Edit recurring expense' : 'New recurring expense')}
                    </SheetTitle>
                    <SheetDescription>
                        {t('Future expenses use the current schedule values; generated expenses never change.')}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-x-hidden overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label>{t('Property')}</Label>
                            <SearchableSelect
                                options={propertyOptions}
                                value={data.property_id || null}
                                onChange={(value) =>
                                    setData('property_id', value === null ? '' : String(value))
                                }
                                placeholder={t('Select property...')}
                                searchPlaceholder={t('Search property...')}
                                emptyText={t('No properties found.')}
                            />
                            <InputError message={errors.property_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recurring-expense-category">
                                {t('Category')}
                            </Label>
                            <Select
                                value={data.expense_category_id}
                                onValueChange={(value) => setData('expense_category_id', value)}
                            >
                                <SelectTrigger id="recurring-expense-category">
                                    <SelectValue placeholder={t('Select category')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableCategories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.label}
                                            {!category.is_active && ` (${t('archived')})`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.expense_category_id} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="recurring-expense-amount">{t('Amount')}</Label>
                                <Input
                                    id="recurring-expense-amount"
                                    type="number"
                                    min="0"
                                    step="any"
                                    inputMode="decimal"
                                    required
                                    value={data.amount}
                                    onChange={(event) => setData('amount', event.target.value)}
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label>{t('Currency')}</Label>
                                <Select
                                    value={data.currency}
                                    onValueChange={(value) => setData('currency', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select currency')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencyOptions.map((currency) => (
                                            <SelectItem key={currency} value={currency}>
                                                {currency}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.currency} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('Billing period')}</Label>
                            <div className="grid grid-cols-[5rem_minmax(0,1fr)] gap-2">
                                <Input
                                    aria-label={t('Billing interval')}
                                    type="number"
                                    min={1}
                                    required
                                    value={data.billing_interval}
                                    onChange={(event) =>
                                        setData('billing_interval', event.target.value)
                                    }
                                />
                                <Select
                                    value={data.billing_unit}
                                    onValueChange={(value) =>
                                        setData('billing_unit', value as RecurringExpense['billing_unit'])
                                    }
                                >
                                    <SelectTrigger aria-label={t('Billing unit')}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BILLING_UNITS.map((unit) => (
                                            <SelectItem key={unit} value={unit}>
                                                {t(unit)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <InputError
                                message={errors.billing_interval ?? errors.billing_unit}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="recurring-expense-start">{t('Start date')}</Label>
                                <Input
                                    id="recurring-expense-start"
                                    type="date"
                                    required
                                    value={data.start_date}
                                    onChange={(event) => setData('start_date', event.target.value)}
                                />
                                <InputError message={errors.start_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="recurring-expense-end">{t('End date')}</Label>
                                <Input
                                    id="recurring-expense-end"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(event) => setData('end_date', event.target.value)}
                                />
                                <InputError message={errors.end_date} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recurring-expense-vendor">{t('Vendor / Payee')}</Label>
                            <Input
                                id="recurring-expense-vendor"
                                value={data.vendor}
                                onChange={(event) => setData('vendor', event.target.value)}
                                placeholder={t('Optional')}
                            />
                            <InputError message={errors.vendor} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recurring-expense-description">{t('Description')}</Label>
                            <Textarea
                                id="recurring-expense-description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder={t('Optional')}
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t pt-4">
                        <Button type="button" variant="outline" onClick={() => close(false)} disabled={processing}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t(processing ? 'Saving...' : 'Save')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
