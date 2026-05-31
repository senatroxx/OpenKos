import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import tenants from '@/routes/tenants';

type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
};

export default function TenantFormSheet({
    tenant,
    open,
    onOpenChange,
}: {
    tenant?: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(tenant);
    const formAction = isEdit
        ? tenants.update.url(tenant!.id)
        : tenants.store.url();
    const formMethod = isEdit ? ('put' as const) : ('post' as const);

    return (
        <Sheet
            key={tenant?.id ?? 'new'}
            open={open}
            onOpenChange={onOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isEdit ? 'Edit Tenant' : 'New Tenant'}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update tenant details'
                            : 'Add a new tenant to the system'}
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
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={tenant?.name ?? ''}
                                        placeholder="e.g. Budi Santoso"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        defaultValue={tenant?.phone ?? ''}
                                        placeholder="e.g. 081234567890"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="id_card_number">
                                        ID Card Number (KTP)
                                    </Label>
                                    <Input
                                        id="id_card_number"
                                        name="id_card_number"
                                        defaultValue={
                                            tenant?.id_card_number ?? ''
                                        }
                                        placeholder="e.g. 3273010203040005"
                                    />
                                    <InputError
                                        message={errors.id_card_number}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="emergency_contact_name">
                                            Emergency Contact Name
                                        </Label>
                                        <Input
                                            id="emergency_contact_name"
                                            name="emergency_contact_name"
                                            defaultValue={
                                                tenant?.emergency_contact_name ??
                                                ''
                                            }
                                            placeholder="e.g. Siti Nurhaliza"
                                        />
                                        <InputError
                                            message={
                                                errors.emergency_contact_name
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="emergency_contact_phone">
                                            Emergency Contact Phone
                                        </Label>
                                        <Input
                                            id="emergency_contact_phone"
                                            name="emergency_contact_phone"
                                            defaultValue={
                                                tenant?.emergency_contact_phone ??
                                                ''
                                            }
                                            placeholder="e.g. 081234567891"
                                        />
                                        <InputError
                                            message={
                                                errors.emergency_contact_phone
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        defaultValue={tenant?.notes ?? ''}
                                        placeholder="Additional notes"
                                        className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <input
                                        id="is_active"
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked={
                                            tenant?.is_active ?? true
                                        }
                                        className="rounded border-input"
                                    />
                                    <Label htmlFor="is_active">Active</Label>
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
                                        {isEdit ? 'Save' : 'Create'}
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
