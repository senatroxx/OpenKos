import { router } from '@inertiajs/react';
import { Ban, ExternalLink, FileText, Pencil } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate, formatDateTime, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import expenses from '@/routes/expenses';
import type { Expense } from '@/types';

export default function ExpenseDetailSheet({
    expense,
    open,
    onOpenChange,
    canUpdate,
    canDelete,
    onEdit,
}: {
    expense: Expense | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canUpdate: boolean;
    canDelete: boolean;
    onEdit: () => void;
}) {
    const [voidConfirm, setVoidConfirm] = useState(false);
    const [reason, setReason] = useState('');
    const [voiding, setVoiding] = useState(false);

    if (!expense) {
        return null;
    }

    const isVoided = expense.status === 'voided';

    function confirmVoid() {
        if (voiding) {
            return;
        }

        setVoiding(true);

        router.delete(expenses.destroy.url(expense!), {
            data: { reason: reason || undefined },
            onSuccess: () => {
                setVoidConfirm(false);
                setReason('');
                onOpenChange(false);
            },
            onFinish: () => setVoiding(false),
        });
    }

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {expense.category?.label ?? t('Expense')}
                        </SheetTitle>
                        <SheetDescription>
                            {expense.property?.name ?? t('Property expense')}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-6">
                            <div className="flex items-center gap-2">
                                <StatusBadge
                                    domain="expense"
                                    value={expense.status}
                                />
                                {expense.reference && (
                                    <Badge variant="outline">
                                        {expense.reference}
                                    </Badge>
                                )}
                            </div>

                            <section className="space-y-3 rounded-lg border bg-muted/30 p-4">
                                <DetailRow
                                    label={t('Amount')}
                                    value={formatPrice(
                                        expense.amount,
                                        expense.currency,
                                    )}
                                />
                                <DetailRow
                                    label={t('Date')}
                                    value={formatDate(expense.expense_date)}
                                />
                                <DetailRow
                                    label={t('Property')}
                                    value={expense.property?.name ?? '—'}
                                />
                                <DetailRow
                                    label={t('Category')}
                                    value={expense.category?.label ?? '—'}
                                />
                                <DetailRow
                                    label={t('Vendor / Payee')}
                                    value={expense.vendor ?? '—'}
                                />
                            </section>

                            {expense.description && (
                                <section>
                                    <h3 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        {t('Description')}
                                    </h3>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {expense.description}
                                    </p>
                                </section>
                            )}

                            {expense.notes && (
                                <section>
                                    <h3 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        {t('Notes')}
                                    </h3>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {expense.notes}
                                    </p>
                                </section>
                            )}

                            {expense.receipt && (
                                <section>
                                    <h3 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        {t('Receipt')}
                                    </h3>
                                    <a
                                        href={expense.receipt.download_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-accent"
                                    >
                                        <FileText className="size-4" />
                                        {expense.receipt.original_name}
                                        <ExternalLink className="size-3.5" />
                                    </a>
                                </section>
                            )}

                            {isVoided && (
                                <section className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm">
                                    <p className="font-medium">{t('Voided')}</p>
                                    <p className="mt-1 text-muted-foreground">
                                        {formatDateTime(expense.voided_at)}
                                    </p>
                                    {expense.void_reason && (
                                        <p className="mt-2 whitespace-pre-wrap">
                                            {expense.void_reason}
                                        </p>
                                    )}
                                </section>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-3">
                            {!isVoided && canUpdate && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onEdit}
                                >
                                    <Pencil className="size-3.5" />
                                    {t('Edit')}
                                </Button>
                            )}
                            {!isVoided && canDelete && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => setVoidConfirm(true)}
                                >
                                    <Ban className="size-3.5" />
                                    {t('Void')}
                                </Button>
                            )}
                        </div>
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={voidConfirm}
                onOpenChange={(next) => {
                    setVoidConfirm(next);

                    if (!next) {
                        setReason('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Void expense')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Voided expenses become read-only and stay in the audit history.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <label
                            htmlFor="void_reason"
                            className="text-sm font-medium"
                        >
                            {t('Reason (optional)')}
                        </label>
                        <Input
                            id="void_reason"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setVoidConfirm(false)}
                            disabled={voiding}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmVoid}
                            disabled={voiding}
                        >
                            {t(voiding ? 'Voiding...' : 'Void expense')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}
