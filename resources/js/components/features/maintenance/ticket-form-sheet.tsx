import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { MaintenanceTicket } from '@/types';

type UnitOption = {
    id: number;
    name: string;
    property_id: number;
    status: string;
    active_lease_count: number;
    has_maintenance_transfer?: number;
    leases?: { tenants: { id: number; name: string }[] }[];
};

function formatUnitOption(r: UnitOption): string {
    const tenants = r.leases?.[0]?.tenants ?? [];
    const unitLabel = r.name.length > 20 ? r.name.slice(0, 19) + '...' : r.name;

    if (tenants.length === 0) {
        return unitLabel;
    }

    const names = tenants.map((t) => t.name);

    if (names.length > 2) {
        return `${unitLabel} - ${names.slice(0, 2).join(', ')}, +${names.length - 2}`;
    }

    return `${unitLabel} - ${names.join(', ')}`;
}

export default function TicketFormSheet({
    open,
    onOpenChange,
    ticket,
    properties,
    units,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ticket?: MaintenanceTicket | null;
    properties: { id: number; name: string }[];
    units: UnitOption[];
}) {
    const isEdit = Boolean(ticket);
    const formAction = isEdit
        ? maintenanceTickets.update.url(ticket!.id)
        : maintenanceTickets.store.url();
    const formMethod = isEdit ? ('put' as const) : ('post' as const);

    // UI-only: whether the location is a unit or a free-text common area.
    const [locationType, setLocationType] = useState(
        ticket?.unit_id ? 'unit' : ticket?.location ? 'area' : 'unit',
    );
    const [showOccupiedDialog, setShowOccupiedDialog] = useState(false);
    const [moveToUnitId, setMoveToUnitId] = useState('');
    const [occupantAction, setOccupantAction] = useState<'move' | 'keep'>(
        'move',
    );
    const [showMoveBackDialog, setShowMoveBackDialog] = useState(false);

    const { data, setData, transform, submit, processing, errors } = useForm({
        property_id: ticket?.property_id ? String(ticket.property_id) : '',
        unit_id: ticket?.unit_id ? String(ticket.unit_id) : '',
        location: ticket?.location ?? '',
        block_unit: false,
        title: ticket?.title ?? '',
        description: ticket?.description ?? '',
        priority: ticket?.priority ?? 'medium',
        resolution_notes: ticket?.resolution_notes ?? '',
        cost: ticket?.cost != null ? String(ticket.cost) : '',
        restore_unit: false,
    });

    const filteredUnits = data.property_id
        ? units.filter((r) => r.property_id === Number(data.property_id))
        : [];

    const selectedUnitData = data.unit_id
        ? units.find((r) => r.id === Number(data.unit_id))
        : undefined;

    const selectedUnitOccupied =
        (selectedUnitData?.active_lease_count ?? 0) > 0;

    const availableMoveUnits = data.property_id
        ? units.filter(
              (r) =>
                  r.property_id === Number(data.property_id) &&
                  r.id !== Number(data.unit_id) &&
                  r.status !== 'maintenance' &&
                  r.active_lease_count === 0,
          )
        : [];

    // Build the exact payload the server expects — mirrors what each variant
    // of the form actually renders (create vs edit vs resolved), plus any extra
    // keys the occupied/move-back dialogs contribute.
    function buildPayload(extra: Record<string, unknown> = {}) {
        const payload: Record<string, unknown> = {
            title: data.title,
            description: data.description,
            priority: data.priority,
            ...extra,
        };

        if (isEdit) {
            if (ticket?.status === 'resolved') {
                payload.resolution_notes = data.resolution_notes;
                payload.cost = data.cost;
            }

            payload.restore_unit = data.restore_unit;
        } else {
            payload.property_id = data.property_id;

            if (locationType === 'unit') {
                payload.unit_id = data.unit_id;
            } else {
                payload.location = data.location;
            }

            payload.block_unit = data.block_unit;
        }

        return payload;
    }

    function submitTicket(extra: Record<string, unknown> = {}) {
        transform(() => buildPayload(extra));
        submit(formMethod, formAction, {
            onSuccess: () => onOpenChange(false),
        });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        // Editing a blocked unit back to available may require moving the
        // occupant back first.
        if (isEdit && data.restore_unit && ticket?.unit_id) {
            const unit = units.find((r) => r.id === ticket.unit_id);

            if (unit?.has_maintenance_transfer) {
                setShowMoveBackDialog(true);

                return;
            }
        }

        // Blocking an occupied unit prompts what to do with the tenant.
        if (!isEdit && data.block_unit && selectedUnitOccupied) {
            // Start the dialog fresh: a destination picked for a previous
            // property/unit must not carry over (it may no longer be valid).
            setMoveToUnitId('');
            // Default to "move" only when there's somewhere to move them.
            setOccupantAction(availableMoveUnits.length > 0 ? 'move' : 'keep');
            setShowOccupiedDialog(true);

            return;
        }

        submitTicket();
    }

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {isEdit ? 'Edit Ticket' : 'New Maintenance Ticket'}
                        </SheetTitle>
                        <SheetDescription>
                            {isEdit
                                ? 'Update ticket details.'
                                : 'Report a maintenance issue.'}
                        </SheetDescription>
                    </SheetHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                    >
                        <div className="space-y-6">
                            {!isEdit && (
                                <div className="grid gap-2">
                                    <Label>Property</Label>
                                    <Select
                                        value={data.property_id}
                                        onValueChange={(v) =>
                                            setData((prev) => ({
                                                ...prev,
                                                property_id: v,
                                                unit_id: '',
                                                block_unit: false,
                                            }))
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select property" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {properties.map((p) => (
                                                <SelectItem
                                                    key={p.id}
                                                    value={String(p.id)}
                                                >
                                                    {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.property_id} />
                                </div>
                            )}

                            {!isEdit && (
                                <div className="grid gap-2">
                                    <Label>Location</Label>
                                    <div className="flex gap-2">
                                        <Select
                                            value={locationType}
                                            onValueChange={setLocationType}
                                        >
                                            <SelectTrigger className="w-36 shrink-0">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="unit">
                                                    Unit
                                                </SelectItem>
                                                <SelectItem value="area">
                                                    Common Area
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {locationType === 'unit' ? (
                                            <Select
                                                value={data.unit_id}
                                                onValueChange={(v) =>
                                                    setData((prev) => ({
                                                        ...prev,
                                                        unit_id: v,
                                                        block_unit: false,
                                                    }))
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Select a unit (optional)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {filteredUnits.map((r) => (
                                                        <SelectItem
                                                            key={r.id}
                                                            value={String(r.id)}
                                                        >
                                                            {formatUnitOption(
                                                                r,
                                                            )}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Input
                                                value={data.location}
                                                onChange={(e) =>
                                                    setData(
                                                        'location',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. Lobby, 3rd Floor Hallway"
                                            />
                                        )}
                                    </div>
                                    <InputError
                                        message={
                                            errors.unit_id ?? errors.location
                                        }
                                    />
                                </div>
                            )}

                            {!isEdit && data.unit_id && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="block_unit"
                                        checked={data.block_unit}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'block_unit',
                                                Boolean(checked),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor="block_unit"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Block unit for maintenance
                                    </Label>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label>Title</Label>
                                <Input
                                    required={!isEdit}
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    placeholder="e.g. Leaking faucet"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Describe the issue in detail"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Priority</Label>
                                <Select
                                    value={data.priority}
                                    onValueChange={(v) =>
                                        setData('priority', v)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">
                                            Medium
                                        </SelectItem>
                                        <SelectItem value="high">
                                            High
                                        </SelectItem>
                                        <SelectItem value="urgent">
                                            Urgent
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.priority} />
                            </div>

                            {isEdit && ticket?.status === 'resolved' && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="resolution_notes">
                                            Resolution Notes
                                        </Label>
                                        <Textarea
                                            id="resolution_notes"
                                            value={data.resolution_notes}
                                            onChange={(e) =>
                                                setData(
                                                    'resolution_notes',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.resolution_notes}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="cost">Cost</Label>
                                        <Input
                                            id="cost"
                                            type="number"
                                            value={data.cost}
                                            onChange={(e) =>
                                                setData('cost', e.target.value)
                                            }
                                        />
                                        <InputError message={errors.cost} />
                                    </div>
                                </>
                            )}

                            {isEdit &&
                                ticket?.status !== 'resolved' &&
                                ticket?.unit_id &&
                                (() => {
                                    const unit = units.find(
                                        (r) => r.id === ticket!.unit_id,
                                    );
                                    const isBlocked =
                                        unit?.status === 'maintenance';

                                    if (!isBlocked) {
                                        return null;
                                    }

                                    return (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="restore_unit"
                                                checked={data.restore_unit}
                                                onCheckedChange={(v) =>
                                                    setData(
                                                        'restore_unit',
                                                        Boolean(v),
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor="restore_unit"
                                                className="cursor-pointer text-sm font-normal"
                                            >
                                                Restore unit availability
                                            </Label>
                                        </div>
                                    );
                                })()}
                        </div>
                        <div className="flex items-center justify-end gap-4">
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => onOpenChange(false)}
                                disabled={processing}
                            >
                                Cancel
                            </Button>
                            <Button disabled={processing} type="submit">
                                {isEdit ? 'Save' : 'Create'}
                            </Button>
                        </div>
                    </form>
                </SheetContent>
            </Sheet>

            <Dialog
                open={showOccupiedDialog}
                onOpenChange={setShowOccupiedDialog}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Unit Occupied</DialogTitle>
                        <DialogDescription className="text-sm text-muted-foreground">
                            {selectedUnitData?.name} has an active lease. What
                            would you like to do?
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="flex items-start gap-3">
                            <div className="flex w-full flex-col gap-3">
                                {availableMoveUnits.length > 0 && (
                                    <label className="flex cursor-pointer items-center gap-3">
                                        <input
                                            type="radio"
                                            name="occupant_action"
                                            checked={occupantAction === 'move'}
                                            onChange={() =>
                                                setOccupantAction('move')
                                            }
                                            className="mt-0.5"
                                        />
                                        <div className="flex-1">
                                            <div className="text-sm font-medium">
                                                Move tenant to another unit
                                            </div>
                                            <Select
                                                value={moveToUnitId}
                                                onValueChange={(v) => {
                                                    setMoveToUnitId(v);
                                                    setOccupantAction('move');
                                                }}
                                            >
                                                <SelectTrigger className="mt-2 w-full">
                                                    <SelectValue placeholder="Select unit" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableMoveUnits.map(
                                                        (r) => (
                                                            <SelectItem
                                                                key={r.id}
                                                                value={String(
                                                                    r.id,
                                                                )}
                                                            >
                                                                {formatUnitOption(
                                                                    r,
                                                                )}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </label>
                                )}
                                <label className="flex cursor-pointer items-center gap-3">
                                    <input
                                        type="radio"
                                        name="occupant_action"
                                        checked={occupantAction === 'keep'}
                                        onChange={() =>
                                            setOccupantAction('keep')
                                        }
                                        className="mt-0.5"
                                    />
                                    <span className="text-sm font-medium">
                                        Keep tenant, just mark as maintenance
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowOccupiedDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={
                                occupantAction === 'move' && !moveToUnitId
                            }
                            onClick={() => {
                                setShowOccupiedDialog(false);
                                submitTicket(
                                    occupantAction === 'move' && moveToUnitId
                                        ? {
                                              move_tenant_to_unit_id:
                                                  moveToUnitId,
                                          }
                                        : {},
                                );
                            }}
                        >
                            Continue
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={showMoveBackDialog}
                onOpenChange={setShowMoveBackDialog}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Restore Occupant?</DialogTitle>
                        <DialogDescription>
                            This unit was vacated for maintenance. Move the
                            occupant back?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowMoveBackDialog(false);
                                submitTicket();
                            }}
                        >
                            Keep in current unit
                        </Button>
                        <Button
                            onClick={() => {
                                setShowMoveBackDialog(false);
                                submitTicket({ move_back: '1' });
                            }}
                        >
                            Move back
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
