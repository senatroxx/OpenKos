import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
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
import leases from '@/routes/leases';

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
};

type RoomInfo = {
    id: number;
    name: string;
    property_id: number;
    property: { id: number; name: string; city: { name: string } | null } | null;
};

type LeaseData = {
    id: number;
    tenant: TenantInfo | null;
    room: RoomInfo | null;
};

type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    property: { id: number; name: string; city: { name: string } | null } | null;
};

const REASONS = [
    { value: 'contract_ended', label: 'Contract ended' },
    { value: 'moved_room', label: 'Moved room' },
    { value: 'left_early', label: 'Left early' },
    { value: 'evicted', label: 'Evicted' },
    { value: 'other', label: 'Other' },
];

export default function MoveOutSheet({
    lease,
    availableRooms,
    open,
    onOpenChange,
    onClose,
}: {
    lease?: LeaseData | null;
    availableRooms?: AvailableRoom[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose?: () => void;
}) {
    const [reason, setReason] = useState('');
    const [depositReturned, setDepositReturned] = useState<string | null>(null);
    const [moveToAnotherRoom, setMoveToAnotherRoom] = useState(false);
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(null);
    const [selectedTargetRoomId, setSelectedTargetRoomId] = useState<number | null>(null);

    const propertyOptions = useMemo(() => {
        const seen = new Set<number>();

        return (availableRooms ?? [])
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
    }, [availableRooms]);

    const filteredRooms = useMemo(
        () =>
            selectedPropertyId
                ? (availableRooms ?? []).filter((r) => r.property_id === selectedPropertyId)
                : [],
        [availableRooms, selectedPropertyId],
    );

    const targetRoomOptions = filteredRooms.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    function handlePropertyChange(val: number | string | null) {
        setSelectedPropertyId(val as number | null);
        setSelectedTargetRoomId(null);
    }

    function handleClose() {
        onOpenChange(false);
        onClose?.();
    }

    const tenantName = lease?.tenant?.name ?? 'Unknown';
    const roomName = lease?.room?.name ?? 'Unknown';
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
                        {tenantName} · {roomName}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={leases.moveOut.post({ lease: lease!.id }).url}
                        method="post"
                        onSuccess={handleClose}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">{tenantName}</span>
                                        <span className="text-muted-foreground">{roomName}</span>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="move_out_date">Move-out Date</Label>
                                    <Input
                                        id="move_out_date"
                                        name="move_out_date"
                                        type="date"
                                        defaultValue={new Date().toISOString().split('T')[0]}
                                        required
                                    />
                                    <InputError message={errors.move_out_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <input type="hidden" name="reason" value={reason} />
                                    <Select value={reason} onValueChange={setReason}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select reason (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {REASONS.map((r) => (
                                                <SelectItem key={r.value} value={r.value}>
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.reason} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Deposit Returned?</Label>
                                    <input
                                        type="hidden"
                                        name="deposit_returned"
                                        value={depositReturned === 'yes' ? '1' : '0'}
                                    />
                                    <div className="flex items-center gap-4">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="deposit_returned_radio"
                                                checked={depositReturned === 'yes'}
                                                onChange={() => setDepositReturned('yes')}
                                                className="size-4"
                                            />
                                            Yes
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="deposit_returned_radio"
                                                checked={depositReturned === 'no'}
                                                onChange={() => setDepositReturned('no')}
                                                className="size-4"
                                            />
                                            No
                                        </label>
                                    </div>
                                    <InputError message={errors.deposit_returned} />
                                </div>

                                {depositReturned === 'yes' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="deposit_refund_amount">
                                            Refund Amount (IDR)
                                        </Label>
                                        <Input
                                            id="deposit_refund_amount"
                                            name="deposit_refund_amount"
                                            type="number"
                                            min={0}
                                            placeholder="Leave empty for full deposit"
                                        />
                                        <InputError message={errors.deposit_refund_amount} />
                                    </div>
                                )}

                                <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={moveToAnotherRoom}
                                        onChange={(e) => {
                                            setMoveToAnotherRoom(e.target.checked);

                                            if (!e.target.checked) {
                                                setSelectedPropertyId(null);
                                                setSelectedTargetRoomId(null);
                                            }
                                        }}
                                        className="mt-0.5 size-4"
                                    />
                                    <div>
                                        <span className="font-medium">Moving to another room?</span>
                                        <p className="text-xs text-muted-foreground">
                                            Terminate this lease and create a new one in a different
                                            room. Deposit carries forward.
                                        </p>
                                    </div>
                                </label>

                                <input
                                    type="hidden"
                                    name="move_to_another_room"
                                    value={moveToAnotherRoom ? '1' : '0'}
                                />

                                {moveToAnotherRoom && (
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
                                                emptyText="No available rooms."
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Target Room</Label>
                                            <input
                                                type="hidden"
                                                name="target_room_id"
                                                value={selectedTargetRoomId ?? ''}
                                            />
                                            <SearchableSelect
                                                key={selectedPropertyId ?? 'none'}
                                                options={targetRoomOptions}
                                                value={selectedTargetRoomId}
                                                onChange={(val) =>
                                                    setSelectedTargetRoomId(val as number | null)
                                                }
                                                placeholder={
                                                    selectedPropertyId
                                                        ? 'Select room...'
                                                        : 'Choose a property first'
                                                }
                                                searchPlaceholder="Search room..."
                                                emptyText="No available rooms."
                                                disabled={!selectedPropertyId}
                                            />
                                            <InputError message={errors.target_room_id} />
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Additional notes"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
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
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
