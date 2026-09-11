import { Plus, Trash2 } from 'lucide-react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPrice, todayISO } from '@/lib/formatters';
import { t } from '@/lib/i18n';

export type DepositSettlementFormData = {
    status: 'draft' | 'settled';
    settlement_date: string;
    refund_amount: string;
    deductions: {
        amount: string;
        reason: string;
        description: string;
    }[];
    refund_reference: string;
    notes: string;
};

type Props = {
    value: DepositSettlementFormData;
    originalAmount: string;
    currency: string;
    errors?: Record<string, string | undefined>;
    readOnly?: boolean;
    onChange: (value: DepositSettlementFormData) => void;
};

export function emptyDepositSettlementForm(
    originalAmount: string,
): DepositSettlementFormData {
    return {
        status: 'draft',
        settlement_date: todayISO(),
        refund_amount: originalAmount,
        deductions: [],
        refund_reference: '',
        notes: '',
    };
}

export default function DepositSettlementFields({
    value,
    originalAmount,
    currency,
    errors = {},
    readOnly = false,
    onChange,
}: Props) {
    const update = <K extends keyof DepositSettlementFormData>(
        key: K,
        nextValue: DepositSettlementFormData[K],
    ) => onChange({ ...value, [key]: nextValue });

    const updateDeduction = (
        index: number,
        field: keyof DepositSettlementFormData['deductions'][number],
        fieldValue: string,
    ) => {
        const deductions = value.deductions.map((deduction, deductionIndex) =>
            deductionIndex === index
                ? { ...deduction, [field]: fieldValue }
                : deduction,
        );

        update('deductions', deductions);
    };

    const error = (field: string): string | undefined => errors[field];

    return (
        <div className="space-y-4 rounded-md border bg-muted/20 p-3">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-medium text-muted-foreground">
                        {t('Original deposit')}
                    </p>
                    <p className="font-medium tabular-nums">
                        {formatPrice(originalAmount, currency)}
                    </p>
                </div>
                <p className="max-w-56 text-right text-xs text-muted-foreground">
                    {t(
                        'Refund and deductions must account for the full deposit when settled.',
                    )}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="settlement_date">{t('Settlement Date')}</Label>
                <Input
                    id="settlement_date"
                    type="date"
                    value={value.settlement_date}
                    onChange={(event) =>
                        update('settlement_date', event.target.value)
                    }
                    disabled={readOnly}
                    required={value.status === 'settled'}
                />
                <InputError message={error('settlement_date')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="settlement_refund_amount">
                    {t('Refund Amount')} ({currency})
                </Label>
                <Input
                    id="settlement_refund_amount"
                    type="number"
                    min={0}
                    value={value.refund_amount}
                    onChange={(event) =>
                        update('refund_amount', event.target.value)
                    }
                    disabled={readOnly}
                />
                <InputError message={error('refund_amount')} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <Label>{t('Deductions')}</Label>
                        <p className="text-xs text-muted-foreground">
                            {t('Record each withheld amount with a reason.')}
                        </p>
                    </div>
                    {!readOnly && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                update('deductions', [
                                    ...value.deductions,
                                    {
                                        amount: '',
                                        reason: '',
                                        description: '',
                                    },
                                ])
                            }
                        >
                            <Plus />
                            {t('Add deduction')}
                        </Button>
                    )}
                </div>

                {value.deductions.length === 0 ? (
                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        {t('No deductions recorded.')}
                    </p>
                ) : (
                    value.deductions.map((deduction, index) => (
                        <div
                            key={index}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="grid gap-3 sm:grid-cols-[9rem_1fr_auto] sm:items-end">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`deduction-${index}-amount`}
                                    >
                                        {t('Amount')} ({currency})
                                    </Label>
                                    <Input
                                        id={`deduction-${index}-amount`}
                                        type="number"
                                        min={0}
                                        value={deduction.amount}
                                        onChange={(event) =>
                                            updateDeduction(
                                                index,
                                                'amount',
                                                event.target.value,
                                            )
                                        }
                                        disabled={readOnly}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`deduction-${index}-reason`}
                                    >
                                        {t('Reason')}
                                    </Label>
                                    <Input
                                        id={`deduction-${index}-reason`}
                                        value={deduction.reason}
                                        onChange={(event) =>
                                            updateDeduction(
                                                index,
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        disabled={readOnly}
                                        required
                                    />
                                </div>
                                {!readOnly && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        className="text-muted-foreground hover:text-destructive"
                                        onClick={() =>
                                            update(
                                                'deductions',
                                                value.deductions.filter(
                                                    (_, deductionIndex) =>
                                                        deductionIndex !==
                                                        index,
                                                ),
                                            )
                                        }
                                        aria-label={t('Remove deduction')}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`deduction-${index}-description`}
                                >
                                    {t('Description')}
                                </Label>
                                <Textarea
                                    id={`deduction-${index}-description`}
                                    value={deduction.description}
                                    onChange={(event) =>
                                        updateDeduction(
                                            index,
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    disabled={readOnly}
                                />
                            </div>
                            <InputError
                                message={
                                    error(`deductions.${index}.amount`) ??
                                    error(`deductions.${index}.reason`) ??
                                    error(`deductions.${index}.description`)
                                }
                            />
                        </div>
                    ))
                )}
                <InputError message={error('deductions')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="refund_reference">
                    {t('Refund Reference')} ({t('Optional')})
                </Label>
                <Input
                    id="refund_reference"
                    value={value.refund_reference}
                    onChange={(event) =>
                        update('refund_reference', event.target.value)
                    }
                    disabled={readOnly}
                />
                <InputError message={error('refund_reference')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="settlement_notes">
                    {t('Settlement Notes')} ({t('Optional')})
                </Label>
                <Textarea
                    id="settlement_notes"
                    value={value.notes}
                    onChange={(event) => update('notes', event.target.value)}
                    disabled={readOnly}
                />
                <InputError message={error('notes')} />
            </div>

            <InputError message={error('settlement')} />
        </div>
    );
}
