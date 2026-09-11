import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import leases from '@/routes/leases';
import type { Lease } from '@/types';
import DepositSettlementFields, {
    emptyDepositSettlementForm,
} from './deposit-settlement-fields';
import type { DepositSettlementFormData } from './deposit-settlement-fields';

export default function DepositSettlementSheet({
    lease,
    open,
    onOpenChange,
}: {
    lease?: Lease | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const initialData = useMemo<DepositSettlementFormData>(() => {
        if (!lease?.deposit_settlement) {
            return emptyDepositSettlementForm(lease?.deposit_amount ?? '0');
        }

        return {
            status:
                lease.deposit_settlement.status === 'settled'
                    ? 'settled'
                    : 'draft',
            settlement_date: lease.deposit_settlement.settlement_date,
            refund_amount: lease.deposit_settlement.refund_amount,
            deductions: lease.deposit_settlement.deductions.map(
                (deduction) => ({
                    amount: deduction.amount,
                    reason: deduction.reason,
                    description: deduction.description ?? '',
                }),
            ),
            refund_reference: lease.deposit_settlement.refund_reference ?? '',
            notes: lease.deposit_settlement.notes ?? '',
        };
    }, [lease]);

    const { data, setData, transform, submit, processing, errors } =
        useForm<DepositSettlementFormData>(initialData);

    useEffect(() => {
        if (open) {
            setData(initialData);
        }
    }, [initialData, open, setData]);

    if (!lease) {
        return null;
    }

    const currentLease = lease;
    const settlement = currentLease.deposit_settlement;
    const isReadOnly = settlement?.status === 'settled';

    function save(status: 'draft' | 'settled') {
        transform((current) => ({ ...current, status }));
        submit(leases.depositSettlement({ lease: currentLease.id }), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isReadOnly
                            ? t('Deposit Settlement')
                            : settlement
                              ? t('Edit Deposit Settlement')
                              : t('Settle Deposit')}
                    </SheetTitle>
                    <SheetDescription>
                        {lease.reference ?? `#${lease.id}`} ·{' '}
                        {lease.primary_tenant?.name ?? t('Lease')}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                    <DepositSettlementFields
                        value={data}
                        originalAmount={
                            settlement?.original_amount ?? lease.deposit_amount
                        }
                        currency={settlement?.currency ?? lease.currency}
                        errors={errors as Record<string, string | undefined>}
                        readOnly={isReadOnly}
                        onChange={setData}
                    />

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            {t('Close')}
                        </Button>
                        {!isReadOnly && (
                            <>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => save('draft')}
                                    disabled={processing}
                                >
                                    {t('Save Draft')}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => save('settled')}
                                    disabled={processing}
                                >
                                    {t('Settle Deposit')}
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
