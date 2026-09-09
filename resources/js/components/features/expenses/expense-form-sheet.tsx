import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
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
import { todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import expenses from '@/routes/expenses';
import type { Expense, ExpenseCategory } from '@/types';

type ExpenseFormData = {
    property_id: string;
    expense_category_id: string;
    amount: string;
    currency: string;
    expense_date: string;
    vendor: string;
    description: string;
    notes: string;
    reference: string;
    receipt: File | null;
    remove_receipt: boolean;
};

export default function ExpenseFormSheet({
    expense,
    properties,
    categories,
    currencies,
    open,
    onOpenChange,
}: {
    expense?: Expense | null;
    properties: { id: number; name: string }[];
    categories: ExpenseCategory[];
    currencies: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(expense);
    const [receiptName, setReceiptName] = useState<string | null>(null);
    const { data, setData, submit, reset, processing, errors } =
        useForm<ExpenseFormData>({
            property_id: expense?.property_id
                ? String(expense.property_id)
                : properties[0]
                  ? String(properties[0].id)
                  : '',
            expense_category_id: expense?.expense_category_id
                ? String(expense.expense_category_id)
                : (categories
                      .find((category) => category.is_active)
                      ?.id.toString() ?? ''),
            amount: expense?.amount ?? '',
            currency: expense?.currency ?? currencies[0] ?? '',
            expense_date: expense?.expense_date ?? todayISO(),
            vendor: expense?.vendor ?? '',
            description: expense?.description ?? '',
            notes: expense?.notes ?? '',
            reference: expense?.reference ?? '',
            receipt: null,
            remove_receipt: false,
        });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setReceiptName(null);
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        submit(isEdit ? expenses.update(expense!) : expenses.store(), {
            forceFormData: true,
            onSuccess: () => handleOpenChange(false),
        });
    }

    const availableCategories = categories.filter(
        (category) =>
            category.is_active || category.id === expense?.expense_category_id,
    );

    return (
        <Sheet
            key={expense?.id ?? 'new'}
            open={open}
            onOpenChange={handleOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {t(isEdit ? 'Edit Expense' : 'New Expense')}
                    </SheetTitle>
                    <SheetDescription>
                        {t(
                            isEdit
                                ? 'Update the expense details.'
                                : 'Record an operating expense for a property.',
                        )}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="property_id">{t('Property')}</Label>
                            <Select
                                value={data.property_id}
                                onValueChange={(value) =>
                                    setData('property_id', value)
                                }
                            >
                                <SelectTrigger id="property_id">
                                    <SelectValue
                                        placeholder={t('Select property')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {properties.map((property) => (
                                        <SelectItem
                                            key={property.id}
                                            value={String(property.id)}
                                        >
                                            {property.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.property_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expense_category_id">
                                {t('Category')}
                            </Label>
                            <Select
                                value={data.expense_category_id}
                                onValueChange={(value) =>
                                    setData('expense_category_id', value)
                                }
                            >
                                <SelectTrigger id="expense_category_id">
                                    <SelectValue
                                        placeholder={t('Select category')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableCategories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.label}
                                            {!category.is_active &&
                                                ' (' + t('archived') + ')'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.expense_category_id} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="amount">{t('Amount')}</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    min="0"
                                    step="any"
                                    inputMode="decimal"
                                    required
                                    value={data.amount}
                                    onChange={(event) =>
                                        setData('amount', event.target.value)
                                    }
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="currency">
                                    {t('Currency')}
                                </Label>
                                <Select
                                    value={data.currency}
                                    onValueChange={(value) =>
                                        setData('currency', value)
                                    }
                                    disabled={isEdit}
                                >
                                    <SelectTrigger id="currency">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                {isEdit && (
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Currency is fixed after the expense is recorded.',
                                        )}
                                    </p>
                                )}
                                <InputError message={errors.currency} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expense_date">{t('Date')}</Label>
                            <Input
                                id="expense_date"
                                type="date"
                                required
                                value={data.expense_date}
                                onChange={(event) =>
                                    setData('expense_date', event.target.value)
                                }
                            />
                            <InputError message={errors.expense_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="vendor">
                                {t('Vendor / Payee')}
                            </Label>
                            <Input
                                id="vendor"
                                value={data.vendor}
                                onChange={(event) =>
                                    setData('vendor', event.target.value)
                                }
                                placeholder={t('Optional')}
                            />
                            <InputError message={errors.vendor} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reference">{t('Reference')}</Label>
                            <Input
                                id="reference"
                                value={data.reference}
                                onChange={(event) =>
                                    setData('reference', event.target.value)
                                }
                                placeholder={t('Invoice or receipt number')}
                            />
                            <InputError message={errors.reference} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                {t('Description')}
                            </Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('Notes')}</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                            />
                            <InputError message={errors.notes} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="receipt">{t('Receipt')}</Label>
                            <Input
                                id="receipt"
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={(event) => {
                                    const file =
                                        event.target.files?.[0] ?? null;
                                    setData('receipt', file);
                                    setReceiptName(file?.name ?? null);
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'One optional JPG, PNG, or PDF receipt, up to 10 MB.',
                                )}
                            </p>
                            {receiptName && (
                                <p className="text-sm text-muted-foreground">
                                    {t('Selected:')} {receiptName}
                                </p>
                            )}
                            {isEdit && expense?.receipt && !receiptName && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.remove_receipt}
                                        onChange={(event) =>
                                            setData(
                                                'remove_receipt',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    {t('Remove current receipt')}
                                </label>
                            )}
                            <InputError message={errors.receipt} />
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
                            {t(isEdit ? 'Save' : 'Record Expense')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
