import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import tenants from '@/routes/tenants';

type Room = {
    id: number;
    name: string;
    property_id: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

type AvailableRooms = Room[];

type Tenant = {
    id: number;
    name: string;
};

const DUE_DAY_OPTIONS = [
    { value: '1', label: '1st' },
    { value: '5', label: '5th' },
    { value: '10', label: '10th' },
    { value: '15', label: '15th' },
    { value: '20', label: '20th' },
    { value: '25', label: '25th' },
    { value: '31', label: 'Last day of month' },
];

export default function AssignRoomSheet({
    tenant,
    availableRooms,
    open,
    onOpenChange,
}: {
    tenant?: Tenant | null;
    availableRooms: AvailableRooms;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(
        null,
    );
    const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null);
    const [dueDay, setDueDay] = useState('1');
    const [hasDeposit, setHasDeposit] = useState(false);
    const dueDayInitialized = useRef(false);

    const propertyOptions = useMemo(() => {
        const seen = new Set<number>();

        return availableRooms
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
                ? availableRooms.filter(
                      (r) => r.property_id === selectedPropertyId,
                  )
                : [],
        [availableRooms, selectedPropertyId],
    );

    const roomOptions = filteredRooms.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    function handlePropertyChange(val: number | string | null) {
        setSelectedPropertyId(val as number | null);
        setSelectedRoomId(null);
    }

    function handleStartDateChange(e: React.ChangeEvent<HTMLInputElement>) {
        if (dueDayInitialized.current) {
            return;
        }

        const day = e.target.value
            ? parseInt(e.target.value.split('-')[2], 10)
            : NaN;

        if (!isNaN(day) && day >= 1 && day <= 31) {
            const match = DUE_DAY_OPTIONS.find(
                (o) => parseInt(o.value, 10) === day,
            );

            if (match) {
                setDueDay(match.value);
            }
        }

        dueDayInitialized.current = true;
    }

    return (
        <Sheet key="assign-room" open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Assign to Room</SheetTitle>
                    <SheetDescription>
                        Assign {tenant?.name ?? 'tenant'} to a room
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-4">
                    <Form
                        action={
                            tenants.assignRoom.post({ tenant: tenant!.id }).url
                        }
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-6 pt-4">
                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Section 1 — Who
                                    </h3>

                                    <div className="mb-3 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                                        {tenant?.name ?? 'Unknown tenant'}
                                    </div>

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
                                            emptyText="No properties with available rooms."
                                        />
                                    </div>

                                    <div className="mt-3 grid gap-2">
                                        <Label>Room</Label>
                                        <input
                                            type="hidden"
                                            name="room_id"
                                            value={selectedRoomId ?? ''}
                                        />
                                        <SearchableSelect
                                            key={selectedPropertyId ?? 'none'}
                                            options={roomOptions}
                                            value={selectedRoomId}
                                            onChange={(val) =>
                                                setSelectedRoomId(
                                                    val as number | null,
                                                )
                                            }
                                            placeholder={
                                                selectedPropertyId
                                                    ? 'Select room...'
                                                    : 'Choose a property first'
                                            }
                                            searchPlaceholder="Search room..."
                                            emptyText="No available rooms in this property."
                                            disabled={!selectedPropertyId}
                                        />
                                        <InputError message={errors.room_id} />
                                    </div>
                                </section>

                                <section>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Section 2 — Stay
                                    </h3>

                                    <div className="grid gap-2">
                                        <Label htmlFor="start_date">
                                            Move-in Date
                                        </Label>
                                        <Input
                                            id="start_date"
                                            name="start_date"
                                            type="date"
                                            defaultValue={
                                                new Date()
                                                    .toISOString()
                                                    .split('T')[0]
                                            }
                                            onChange={handleStartDateChange}
                                            required
                                        />
                                        <InputError
                                            message={errors.start_date}
                                        />
                                    </div>

                                    <div className="mt-4 grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="monthly_rent">
                                                Monthly Rent (IDR)
                                            </Label>
                                            <Input
                                                id="monthly_rent"
                                                name="monthly_rent"
                                                type="number"
                                                min={0}
                                                placeholder="Leave empty for room price"
                                            />
                                            <InputError
                                                message={errors.monthly_rent}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="rent_due_day">
                                                Rent Due Every Month
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="rent_due_day"
                                                value={dueDay}
                                            />
                                            <Select
                                                value={dueDay}
                                                onValueChange={setDueDay}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select due day" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {DUE_DAY_OPTIONS.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt.value}
                                                                value={
                                                                    opt.value
                                                                }
                                                            >
                                                                {opt.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.rent_due_day}
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section>
                                    <Collapsible
                                        open={hasDeposit}
                                        onOpenChange={setHasDeposit}
                                        className="space-y-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                Section 3 — Deposit
                                            </h3>
                                            <CollapsibleTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    type="button"
                                                    className="flex items-center gap-2 text-xs text-muted-foreground"
                                                >
                                                    {hasDeposit
                                                        ? 'Has deposit'
                                                        : 'No deposit'}
                                                    <ChevronDown
                                                        className={`size-3 transition-transform ${hasDeposit ? 'rotate-180' : ''}`}
                                                    />
                                                </Button>
                                            </CollapsibleTrigger>
                                        </div>

                                        <CollapsibleContent className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="deposit_amount">
                                                        Deposit Amount (IDR)
                                                    </Label>
                                                    <Input
                                                        id="deposit_amount"
                                                        name="deposit_amount"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={0}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.deposit_amount
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="deposit_paid_at">
                                                        Paid Date
                                                    </Label>
                                                    <Input
                                                        id="deposit_paid_at"
                                                        name="deposit_paid_at"
                                                        type="date"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.deposit_paid_at
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </section>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Additional notes"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex items-center justify-end gap-4 pt-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={() => onOpenChange(false)}
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>
                                        Assign to Room
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
