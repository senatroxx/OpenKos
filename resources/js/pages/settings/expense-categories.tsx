import { Head, router, useForm } from '@inertiajs/react';
import { Archive, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { t } from '@/lib/i18n';
import expenseCategories from '@/routes/settings/expense-categories';
import type { ExpenseCategory } from '@/types';

function CategoryFormSheet({
    category,
    open,
    onOpenChange,
}: {
    category: ExpenseCategory | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(category);
    const { data, setData, submit, reset, processing, errors } = useForm({
        label: category?.label ?? '',
        is_active: category?.is_active ?? true,
    });

    function close(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        submit(
            isEdit
                ? expenseCategories.update(category!)
                : expenseCategories.store(),
            {
                onSuccess: () => close(false),
            },
        );
    }

    return (
        <Sheet open={open} onOpenChange={close}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {t(
                            isEdit
                                ? 'Edit expense category'
                                : 'New expense category',
                        )}
                    </SheetTitle>
                    <SheetDescription>
                        {t('Categories are shared across all properties.')}
                    </SheetDescription>
                </SheetHeader>
                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 px-4 pt-4 pb-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="label">{t('Name')}</Label>
                        <Input
                            id="label"
                            value={data.label}
                            onChange={(event) =>
                                setData('label', event.target.value)
                            }
                            placeholder={t('e.g. Landscaping')}
                            required
                        />
                        <InputError message={errors.label} />
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                            disabled={processing}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button disabled={processing}>
                            {t(isEdit ? 'Save' : 'Add')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function ExpenseCategories({
    categories,
}: {
    categories: ExpenseCategory[];
}) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ExpenseCategory | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ExpenseCategory | null>(
        null,
    );

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(category: ExpenseCategory) {
        setEditing(category);
        setFormOpen(true);
    }

    function toggleActive(category: ExpenseCategory, isActive: boolean) {
        router.patch(expenseCategories.update.url(category), {
            label: category.label,
            is_active: isActive,
        });
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return;
        }

        router.delete(expenseCategories.destroy.url(pendingDelete), {
            onFinish: () => setPendingDelete(null),
        });
    }

    return (
        <>
            <Head title={t('Expense Categories')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium">
                            {t('Expense Categories')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Manage the installation-wide categories available when recording expenses.',
                            )}
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('Add category')}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Categories')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Referenced categories are archived instead of deleted so historical expenses retain their category.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">
                                            {t('Label')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('In use')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('Active')}
                                        </th>
                                        <th className="px-4 py-3 font-medium" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.map((category) => {
                                        const inUse =
                                            (category.expenses_count ?? 0) > 0 ||
                                            (category.recurring_expenses_count ?? 0) > 0;

                                        return (
                                            <tr
                                                key={category.slug}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {category.label}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                    {(category.expenses_count ?? 0) +
                                                        (category.recurring_expenses_count ??
                                                            0)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Switch
                                                        checked={
                                                            category.is_active
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleActive(
                                                                category,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            onClick={() =>
                                                                openEdit(
                                                                    category,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            onClick={() =>
                                                                setPendingDelete(
                                                                    category,
                                                                )
                                                            }
                                                        >
                                                            {inUse ? (
                                                                <Archive className="size-4" />
                                                            ) : (
                                                                <Trash2 className="size-4" />
                                                            )}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <CategoryFormSheet
                key={editing?.id ?? 'new'}
                category={editing}
                open={formOpen}
                onOpenChange={setFormOpen}
            />

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(next) => !next && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                (pendingDelete?.expenses_count ?? 0) > 0
                                    ? 'Archive expense category'
                                    : 'Delete expense category',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {(pendingDelete?.expenses_count ?? 0) > 0
                                ? t(
                                      'This category is referenced by historical expenses and will be archived.',
                                  )
                                : t('Delete this unused category?')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPendingDelete(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {t(
                                (pendingDelete?.expenses_count ?? 0) > 0
                                    ? 'Archive'
                                    : 'Delete',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ExpenseCategories.layout = {
    breadcrumbs: [
        {
            title: 'Expense Categories',
            href: expenseCategories.index(),
        },
    ],
};
