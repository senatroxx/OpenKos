import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { moveOut } from '@/actions/App/Http/Controllers/LeaseController';
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
import { MOVE_OUT_REASONS } from '@/lib/constants/lease';
import type { AvailableUnit, LeaseData } from '@/types';

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
    const { data, setData, processing, errors, submit } = useForm({
        move_out_date: new Date().toISOString().split('T')[0],
        reason: '',
        deposit_returned: '0',
        deposit_refund_amount: '',
        notes: '',
        move_to_another_unit: '0',
        target_unit_id: '',
    });
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(
        null,
    );
    const [selectedTargetUnitId, setSelectedTargetUnitId] = useState<
        number | null
    >(null);

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

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(moveOut(lease!.id), {
            onSuccess: handleClose,
        });
    }

    function handleClose() {
        onOpenChange(false);
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
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Move Out Tenant</SheetTitle>
                    <SheetDescription>
                        {tenantName} · {unitLabel}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <form onSubmit={handleSubmit}>
                            <div className="space-y-6 pt-4">
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
                                    <Label htmlFor="move_out_date">
                                        Move-out Date
                                    </Label>
                                    <Input
                                        id="move_out_date"
                                        name="move_out_date"
                                        type="date"
                                        value={data.move_out_date}
                                        onChange={(e) => setData('move_out_date', e.target.value)}
                                        required
                                    />
                                    <InputError
                                        message={errors.move_out_date}
                                    />
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
                                            {MOVE_OUT_REASONS.map((r) => (
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
                                                name="deposit_returned"
                                                checked={
                                                    data.deposit_returned === '1'
                                                }
                                                onChange={() =>
                                                    setData('deposit_returned', '1')
                                                }
                                                className="size-4"
                                            />
                                            Yes
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="deposit_returned"
                                                checked={
                                                    data.deposit_returned === '0'
                                                }
                                                onChange={() =>
                                                    setData('deposit_returned', '0')
                                                }
                                                className="size-4"
                                            />
                                            No
                                        </label>
                                    </div>
                                    <InputError
                                        message={errors.deposit_returned}
                                    />
                                </div>

                                {data.deposit_returned === '1' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="deposit_refund_amount">
                                            Refund Amount (IDR)
                                        </Label>
                                        <Input
                                            id="deposit_refund_amount"
                                            name="deposit_refund_amount"
                                            type="number"
                                            min={0}
                                            value={data.deposit_refund_amount}
                                            onChange={(e) => setData('deposit_refund_amount', e.target.value)}
                                            placeholder="Leave empty for full deposit"
                                        />
                                        <InputError
                                            message={
                                                errors.deposit_refund_amount
                                            }
                                        />
                                    </div>
                                )}

                                <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.move_to_another_unit === '1'}
                                        onChange={(e) => {
                                            setData('move_to_another_unit', e.target.checked ? '1' : '0');

                                            if (!e.target.checked) {
                                                setSelectedPropertyId(null);
                                                setSelectedTargetUnitId(null);
                                                setData('target_unit_id', '');
                                            }
                                        }}
                                        className="mt-0.5 size-4"
                                    />
                                    <div>
                                        <span className="font-medium">
                                            Moving to another unit?
                                        </span>
                                        <p className="text-xs text-muted-foreground">
                                            Terminate this lease and create a
                                            new one in a different unit. Deposit
                                            carries forward.
                                        </p>
                                    </div>
                                </label>

                                {data.move_to_another_unit === '1' && (
                                    <div className="space-y-3 rounded-md border p-3">
                                        <div className="grid gap-2">
                                            <Label>Property</Label>
                                            <SearchableSelect
                                                options={propertyOptions.map(
                                                    (p) => ({
                                                        value: p.propertyId,
                                                        label: p.label,
                                                    }),
                                                )}
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
                                                key={
                                                    selectedPropertyId ?? 'none'
                                                }
                                                options={targetUnitOptions}
                                                value={selectedTargetUnitId}
                                                onChange={(val) => {
                                                    setSelectedTargetUnitId(
                                                        val as number | null,
                                                    );
                                                    setData('target_unit_id', String(val ?? ''));
                                                }}
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
                                                message={errors.target_unit_id}
                                            />
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Additional notes"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={handleClose}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Move Out
                                    </Button>
                                </div>
                            </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
