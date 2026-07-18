import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
import { todayISO } from '@/lib/formatters';
import leases from '@/routes/leases';
import type { AvailableUnit, LeaseData } from '@/types';

const REASONS = [
    { value: 'contract_ended', label: 'Contract ended' },
    { value: 'moved_unit', label: 'Moved unit' },
    { value: 'left_early', label: 'Left early' },
    { value: 'evicted', label: 'Evicted' },
    { value: 'other', label: 'Other' },
];

export default function MoveOutSheet({
    lease,
    availableUnits,
    open,
    onOpenChange,
    onClose,
}: {
    lease?: LeaseData | null;
    availableUnits?: AvailableUnit[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose?: () => void;
}) {
    const { data, setData, transform, submit, reset, processing, errors } =
        useForm({
            move_out_date: todayISO(),
            reason: '',
            deposit_refund_amount: '',
            notes: '',
        });

    const [depositReturned, setDepositReturned] = useState<string | null>(null);
    const [moveToAnotherUnit, setMoveToAnotherUnit] = useState(false);
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(
        null,
    );
    const [selectedTargetUnitId, setSelectedTargetUnitId] = useState<
        number | null
    >(null);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => {
            const payload: Record<string, unknown> = {
                ...d,
                deposit_returned: depositReturned === 'yes' ? '1' : '0',
                move_to_another_unit: moveToAnotherUnit ? '1' : '0',
            };

            if (depositReturned !== 'yes') {
                delete payload.deposit_refund_amount;
            }

            if (moveToAnotherUnit) {
                payload.target_unit_id = selectedTargetUnitId ?? '';
            }

            return payload;
        });
        submit(leases.moveOut({ lease: lease!.id }), {
            onSuccess: handleClose,
        });
    }

    const propertyOptions = useMemo(() => {
        const seen = new Set<number>();

        return (availableUnits ?? [])
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
                ? (availableUnits ?? []).filter(
                      (r) => r.property_id === selectedPropertyId,
                  )
                : [],
        [availableUnits, selectedPropertyId],
    );

    const targetUnitOptions = filteredUnits.map((r) => {
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
        setSelectedTargetUnitId(null);
    }

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setDepositReturned(null);
            setMoveToAnotherUnit(false);
            setSelectedPropertyId(null);
            setSelectedTargetUnitId(null);
        }
    }

    function handleClose() {
        handleOpenChange(false);
        onClose?.();
    }

    const tenantName =
        lease?.primary_tenant?.name ?? lease?.tenants?.[0]?.name ?? 'Unknown';
    const tenantList = lease?.tenants ?? [];
    const unitLabel = lease?.unit?.name ?? 'Unknown';
    const isCurrentlyOccupied = Boolean(lease);

    if (!isCurrentlyOccupied) {
        return null;
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Move Out Tenant</SheetTitle>
                    <SheetDescription>
                        {tenantName} · {unitLabel}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="rounded-md border bg-muted/30 p-3 text-sm">
                            <div className="space-y-1">
                                {tenantList.length > 0 ? (
                                    tenantList.map((t) => (
                                        <div
                                            key={t.id}
                                            className="flex items-center justify-between"
                                        >
                                            <span className="font-medium">
                                                {t.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {t.pivot?.is_primary
                                                    ? 'Primary'
                                                    : unitLabel}
                                            </span>
                                        </div>
                                    ))
                                ) : (
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">
                                            {tenantName}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {unitLabel}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="move_out_date">Move-out Date</Label>
                            <Input
                                id="move_out_date"
                                type="date"
                                value={data.move_out_date}
                                onChange={(e) =>
                                    setData('move_out_date', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.move_out_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reason">Reason</Label>
                            <Select
                                value={data.reason}
                                onValueChange={(v) => setData('reason', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select reason (optional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {REASONS.map((r) => (
                                        <SelectItem
                                            key={r.value}
                                            value={r.value}
                                        >
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.reason} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Deposit Returned?</Label>
                            <div className="flex items-center gap-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="deposit_returned_radio"
                                        checked={depositReturned === 'yes'}
                                        onChange={() =>
                                            setDepositReturned('yes')
                                        }
                                        className="size-4"
                                    />
                                    Yes
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="deposit_returned_radio"
                                        checked={depositReturned === 'no'}
                                        onChange={() =>
                                            setDepositReturned('no')
                                        }
                                        className="size-4"
                                    />
                                    No
                                </label>
                            </div>
                            <InputError
                                message={
                                    (errors as Record<string, string>)
                                        .deposit_returned
                                }
                            />
                        </div>

                        {depositReturned === 'yes' && (
                            <div className="grid gap-2">
                                <Label htmlFor="deposit_refund_amount">
                                    Refund Amount (IDR)
                                </Label>
                                <Input
                                    id="deposit_refund_amount"
                                    type="number"
                                    min={0}
                                    value={data.deposit_refund_amount}
                                    onChange={(e) =>
                                        setData(
                                            'deposit_refund_amount',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Leave empty for full deposit"
                                />
                                <InputError
                                    message={errors.deposit_refund_amount}
                                />
                            </div>
                        )}

                        <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={moveToAnotherUnit}
                                onChange={(e) => {
                                    setMoveToAnotherUnit(e.target.checked);

                                    if (!e.target.checked) {
                                        setSelectedPropertyId(null);
                                        setSelectedTargetUnitId(null);
                                    }
                                }}
                                className="mt-0.5 size-4"
                            />
                            <div>
                                <span className="font-medium">
                                    Moving to another unit?
                                </span>
                                <p className="text-xs text-muted-foreground">
                                    Terminate this lease and create a new one in
                                    a different unit. Deposit carries forward.
                                </p>
                            </div>
                        </label>

                        {moveToAnotherUnit && (
                            <div className="space-y-3 rounded-md border p-3">
                                <div className="grid gap-2">
                                    <Label>Property</Label>
                                    <SearchableSelect
                                        options={propertyOptions.map((p) => ({
                                            value: p.propertyId,
                                            label: p.label,
                                        }))}
                                        value={selectedPropertyId}
                                        onChange={handlePropertyChange}
                                        placeholder="Select property..."
                                        searchPlaceholder="Search property..."
                                        emptyText="No available units."
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Target Unit</Label>
                                    <SearchableSelect
                                        key={selectedPropertyId ?? 'none'}
                                        options={targetUnitOptions}
                                        value={selectedTargetUnitId}
                                        onChange={(val) =>
                                            setSelectedTargetUnitId(
                                                val as number | null,
                                            )
                                        }
                                        placeholder={
                                            selectedPropertyId
                                                ? 'Select unit...'
                                                : 'Choose a property first'
                                        }
                                        searchPlaceholder="Search unit..."
                                        emptyText="No available units."
                                        disabled={!selectedPropertyId}
                                    />
                                    <InputError
                                        message={
                                            (errors as Record<string, string>)
                                                .target_unit_id
                                        }
                                    />
                                </div>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Additional notes"
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>
                    <div className="flex items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button disabled={processing}>Move Out</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
