import { useForm } from '@inertiajs/react';
import { InputError, PhoneInput } from '@/components/shared';
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
import type { Tenant } from '@/types';

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

    const { data, setData, processing, errors, submit } = useForm({
        name: tenant?.name ?? '',
        phone: tenant?.phone ?? '',
        email: tenant?.email ?? '',
        id_card_number: tenant?.id_card_number ?? '',
        emergency_contact_phone: tenant?.emergency_contact_phone ?? '',
        emergency_contact_name: tenant?.emergency_contact_name ?? '',
        notes: tenant?.notes ?? '',
        is_active: tenant?.is_active ?? true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(formMethod, formAction, {
            onSuccess: () => onOpenChange(false),
        });
    }

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
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-6 pt-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    required
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Budi Santoso"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <PhoneInput
                                    value={data.phone}
                                    onChange={(v: string) =>
                                        setData('phone', v)
                                    }
                                    placeholder="e.g. 81234567890"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="e.g. john@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="id_card_number">
                                    ID Card Number (KTP)
                                </Label>
                                <Input
                                    id="id_card_number"
                                    value={data.id_card_number}
                                    onChange={(e) =>
                                        setData(
                                            'id_card_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 3273010203040005"
                                />
                                <InputError
                                    message={errors.id_card_number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emergency_contact_phone">
                                    Emergency Contact Phone
                                </Label>
                                <PhoneInput
                                    value={data.emergency_contact_phone}
                                    onChange={(v: string) =>
                                        setData(
                                            'emergency_contact_phone',
                                            v,
                                        )
                                    }
                                    placeholder="e.g. 81234567890"
                                />
                                <InputError
                                    message={errors.emergency_contact_phone}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="emergency_contact_name">
                                    Emergency Contact Name
                                </Label>
                                <Input
                                    id="emergency_contact_name"
                                    value={data.emergency_contact_name}
                                    onChange={(e) =>
                                        setData(
                                            'emergency_contact_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Siti Nurhaliza"
                                />
                                <InputError
                                    message={errors.emergency_contact_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder="Additional notes"
                                    className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
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
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
