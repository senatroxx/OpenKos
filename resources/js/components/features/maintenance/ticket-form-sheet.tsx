import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
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
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import maintenanceTickets from '@/routes/maintenance-tickets';
import type { MaintenanceTicket } from '@/types';

export default function TicketFormSheet({
    open,
    onOpenChange,
    ticket,
    properties,
    rooms,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ticket?: MaintenanceTicket | null;
    properties: { id: number; name: string }[];
    rooms: { id: number; name: string; property_id: number }[];
}) {
    const isEdit = Boolean(ticket);
    const formAction = isEdit
        ? maintenanceTickets.update.url(ticket!.id)
        : maintenanceTickets.store.url();
    const formMethod = isEdit ? ('put' as const) : ('post' as const);

    const [locationType, setLocationType] = useState(ticket?.room_id ? 'room' : ticket?.location ? 'area' : 'room');
    const [selectedProperty, setSelectedProperty] = useState(ticket?.property_id ? String(ticket.property_id) : '');
    const [priority, setPriority] = useState(ticket?.priority ?? 'medium');

    const filteredRooms = selectedProperty
        ? rooms.filter((r) => r.property_id === Number(selectedProperty))
        : [];

    return (
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
                                            onValueChange={setSelectedProperty}
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
                                                    <SelectItem value="room">Room</SelectItem>
                                                    <SelectItem value="area">Common Area</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {locationType === 'room' ? (
                                                <Select name="room_id" defaultValue={ticket?.room_id ? String(ticket.room_id) : undefined}>
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Select a room (optional)" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {filteredRooms.map((r) => (
                                                            <SelectItem key={r.id} value={String(r.id)}>
                                                                {r.name}
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
                                        <InputError message={errors.room_id ?? errors.location} />
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

                                {isEdit && ticket.status === 'resolved' && (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="resolution_notes">Resolution Notes</Label>
                                            <Textarea
                                                id="resolution_notes"
                                                name="resolution_notes"
                                                defaultValue={ticket.resolution_notes ?? ''}
                                            />
                                            <InputError message={errors.resolution_notes} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="cost">Cost</Label>
                                            <Input
                                                id="cost"
                                                name="cost"
                                                type="number"
                                                defaultValue={ticket.cost ?? ''}
                                            />
                                            <InputError message={errors.cost} />
                                        </div>
                                    </>
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
                                    <Button disabled={processing}>{isEdit ? 'Save' : 'Create'}</Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
