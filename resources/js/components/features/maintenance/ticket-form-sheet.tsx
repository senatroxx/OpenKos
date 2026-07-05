import { Form, router } from '@inertiajs/react';
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
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { MaintenanceTicket } from '@/types';

type RoomOption = { id: number; name: string; property_id: number; status: string; active_lease_count: number; has_maintenance_transfer?: number; leases?: { tenants: { id: number; name: string }[] }[] };

function formatRoomOption(r: RoomOption): string {
    const tenants = r.leases?.[0]?.tenants ?? [];
    const roomName = r.name.length > 20 ? r.name.slice(0, 19) + '...' : r.name;

    if (tenants.length === 0) {
        return roomName;
    }

    const names = tenants.map((t) => t.name);

    if (names.length > 2) {
        return `${roomName} - ${names.slice(0, 2).join(', ')}, +${names.length - 2}`;
    }

    return `${roomName} - ${names.join(', ')}`;
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
    units: RoomOption[];
}) {
    const isEdit = Boolean(ticket);
    const formAction = isEdit
        ? maintenanceTickets.update.url(ticket!.id)
        : maintenanceTickets.store.url();
    const formMethod = isEdit ? ('put' as const) : ('post' as const);

    const [locationType, setLocationType] = useState(ticket?.unit_id ? 'unit' : ticket?.location ? 'area' : 'unit');
    const [selectedProperty, setSelectedProperty] = useState(ticket?.property_id ? String(ticket.property_id) : '');
    const [priority, setPriority] = useState(ticket?.priority ?? 'medium');
    const [selectedUnitId, setSelectedUnitId] = useState(ticket?.unit_id ? String(ticket.unit_id) : '');
    const [blockUnit, setBlockUnit] = useState(false);
    const [showOccupiedDialog, setShowOccupiedDialog] = useState(false);
    const [moveToRoomId, setMoveToRoomId] = useState('');
    const [restoreUnit, setRestoreUnit] = useState(false);
    const [showMoveBackDialog, setShowMoveBackDialog] = useState(false);

    const filteredRooms = selectedProperty
        ? units.filter((r) => r.property_id === Number(selectedProperty))
        : [];

    const selectedUnitData = selectedUnitId
        ? units.find((r) => r.id === Number(selectedUnitId))
        : undefined;

    const selectedUnitOccupied = (selectedUnitData?.active_lease_count ?? 0) > 0;

    const availableMoveRooms = selectedProperty
        ? units.filter(
            (r) => r.property_id === Number(selectedProperty)
                && r.id !== Number(selectedUnitId)
                && r.status !== 'maintenance'
                && r.active_lease_count === 0,
        )
        : [];

    const handleCreateClick = (e: React.MouseEvent) => {
        if (isEdit && restoreUnit && ticket?.unit_id) {
            const unit = units.find((r) => r.id === ticket.unit_id);

            if (unit?.has_maintenance_transfer) {
                e.preventDefault();
                setShowMoveBackDialog(true);
            }

            return;
        }

        if (!isEdit && blockUnit && selectedUnitOccupied) {
            e.preventDefault();
            setShowOccupiedDialog(true);
        }
    };

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{isEdit ? 'Edit Ticket' : 'New Maintenance Ticket'}</SheetTitle>
                        <SheetDescription>
                            {isEdit ? 'Update ticket details.' : 'Report a maintenance issue.'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-4">
                        <Form
                            id="ticket-form"
                            action={formAction}
                            method={formMethod}
                            onSuccess={() => onOpenChange(false)}
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-6 pt-4">
                                    {! isEdit && (
                                        <div className="grid gap-2">
                                            <Label>Property</Label>
                                            <Select
                                                name="property_id"
                                                value={selectedProperty}
                                                onValueChange={(v) => {
                                                    setSelectedProperty(v);
                                                    setSelectedUnitId('');
                                                    setBlockUnit(false);
                                                }}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Select property" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {properties.map((p) => (
                                                        <SelectItem key={p.id} value={String(p.id)}>
                                                            {p.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.property_id} />
                                        </div>
                                    )}

                                    {! isEdit && (
                                        <div className="grid gap-2">
                                            <Label>Location</Label>
                                            <div className="flex gap-2">
                                                <Select value={locationType} onValueChange={setLocationType}>
                                                    <SelectTrigger className="w-36 shrink-0">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="unit">Unit</SelectItem>
                                                        <SelectItem value="area">Common Area</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {locationType === 'unit' ? (
                                                    <Select
                                                        name="unit_id"
                                                        value={selectedUnitId}
                                                        onValueChange={(v) => {
                                                            setSelectedUnitId(v);
                                                            setBlockUnit(false);
                                                        }}
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Select a unit (optional)" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {filteredRooms.map((r) => (
                                                                <SelectItem key={r.id} value={String(r.id)}>
                                                                    {formatRoomOption(r)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Input
                                                        name="location"
                                                        defaultValue={ticket?.location ?? ''}
                                                        placeholder="e.g. Lobby, 3rd Floor Hallway"
                                                    />
                                                )}
                                            </div>
                                            <InputError message={errors.unit_id ?? errors.location} />
                                        </div>
                                    )}

                                    {! isEdit && selectedUnitId && (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="block_unit"
                                                name="block_unit"
                                                checked={blockUnit}
                                                onCheckedChange={(checked) => setBlockUnit(Boolean(checked))}
                                                value="1"
                                            />
                                            <Label htmlFor="block_unit" className="cursor-pointer text-sm font-normal">
                                                Block unit for maintenance
                                            </Label>
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label>Title</Label>
                                        <Input
                                            name="title"
                                            required={! isEdit}
                                            defaultValue={ticket?.title ?? ''}
                                            placeholder="e.g. Leaking faucet"
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            defaultValue={ticket?.description ?? ''}
                                            placeholder="Describe the issue in detail"
                                        />
                                        <InputError message={errors.description} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Priority</Label>
                                        <Select name="priority" value={priority} onValueChange={setPriority}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="low">Low</SelectItem>
                                                <SelectItem value="medium">Medium</SelectItem>
                                                <SelectItem value="high">High</SelectItem>
                                                <SelectItem value="urgent">Urgent</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.priority} />
                                    </div>

                                    {isEdit && ticket?.status === 'resolved' && (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="resolution_notes">Resolution Notes</Label>
                                                <Textarea
                                                    id="resolution_notes"
                                                    name="resolution_notes"
                                                    defaultValue={ticket?.resolution_notes ?? ''}
                                                />
                                                <InputError message={errors.resolution_notes} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="cost">Cost</Label>
                                                <Input
                                                    id="cost"
                                                    name="cost"
                                                    type="number"
                                                    defaultValue={ticket?.cost ?? ''}
                                                />
                                                <InputError message={errors.cost} />
                                            </div>
                                        </>
                                    )}

                                    {isEdit && ticket?.status !== 'resolved' && ticket?.unit_id && (
                                        (() => {
                                            const unit = units.find((r) => r.id === ticket!.unit_id);
                                            const isBlocked = unit?.status === 'maintenance';

                                            if (! isBlocked) {
return null;
}

                                            return (
                                                <div className="flex items-center gap-2">
                                                    <Checkbox id="restore_unit" name="restore_unit" value="1" checked={restoreUnit} onCheckedChange={(v) => setRestoreUnit(Boolean(v))} />
                                                    <Label htmlFor="restore_unit" className="cursor-pointer text-sm font-normal">
                                                        Restore unit availability
                                                    </Label>
                                                </div>
                                            );
                                        })()
                                    )}

                                    <div className="flex items-center justify-end gap-4 pt-2">
                                        <Button
                                            variant="outline"
                                            type="button"
                                            onClick={() => onOpenChange(false)}
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                        <Button disabled={processing} type="submit" onClick={handleCreateClick}>
                                            {isEdit ? 'Save' : 'Create'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog open={showOccupiedDialog} onOpenChange={setShowOccupiedDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Unit Occupied</DialogTitle>
                        <DialogDescription className="text-sm text-muted-foreground">
                            {selectedUnitData?.name} has an active lease. What would you like to do?
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="flex items-start gap-3">
                            <div className="flex flex-col gap-3 w-full">
                                {availableMoveRooms.length > 0 && (
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="occupant_action" defaultChecked onChange={() => {}} className="mt-0.5" />
                                        <div className="flex-1">
                                            <div className="text-sm font-medium">Move tenant to another unit</div>
                                            <Select value={moveToRoomId} onValueChange={setMoveToRoomId}>
                                                <SelectTrigger className="mt-2 w-full">
                                                    <SelectValue placeholder="Select unit" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableMoveRooms.map((r) => (
                                                        <SelectItem key={r.id} value={String(r.id)}>
                                                            {formatRoomOption(r)}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </label>
                                )}
                                <label className="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="occupant_action" className="mt-0.5" />
                                    <span className="text-sm font-medium">Keep tenant, just mark as maintenance</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowOccupiedDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                setShowOccupiedDialog(false);
                                const formEl = document.getElementById('ticket-form');

                                if (!formEl) {
return;
}

                                const data: Record<string, string> = {};
                                new FormData(formEl as HTMLFormElement).forEach((v, k) => {
                                    data[k] = String(v);
                                });

                                if (moveToRoomId) {
                                    data.move_tenant_to_unit_id = moveToRoomId;
                                }

                                router.visit(formAction, {
                                    method: formMethod,
                                    data,
                                    onSuccess: () => onOpenChange(false),
                                });
                            }}
                        >
                            Continue
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showMoveBackDialog} onOpenChange={setShowMoveBackDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Restore Occupant?</DialogTitle>
                        <DialogDescription>
                            This unit was vacated for maintenance. Move the occupant back?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => {
                            setShowMoveBackDialog(false);
                            const formEl = document.getElementById('ticket-form');

                            if (!formEl) {
return;
}

                            const data: Record<string, string> = {};
                            new FormData(formEl as HTMLFormElement).forEach((v, k) => {
                                data[k] = String(v);
                            });
                            router.visit(formAction, {
                                method: formMethod,
                                data,
                                onSuccess: () => onOpenChange(false),
                            });
                        }}>
                            Keep in current unit
                        </Button>
                        <Button onClick={() => {
                            setShowMoveBackDialog(false);
                            const formEl = document.getElementById('ticket-form');

                            if (!formEl) {
return;
}

                            const data: Record<string, string> = {};
                            new FormData(formEl as HTMLFormElement).forEach((v, k) => {
                                data[k] = String(v);
                            });
                            data.move_back = '1';
                            router.visit(formAction, {
                                method: formMethod,
                                data,
                                onSuccess: () => onOpenChange(false),
                            });
                        }}>
                            Move back
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
