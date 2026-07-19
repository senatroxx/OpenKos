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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { appAccessStatus } from '@/lib/app-access';
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

    // App access lives on the linked user. Its login email is read-only here once the
    // account is active — changing it goes through the App Access section instead.
    const emailLocked = isEdit && appAccessStatus(tenant?.user) === 'active';

    const { data, setData, transform, submit, reset, processing, errors } =
        useForm({
            name: tenant?.name ?? '',
            phone: tenant?.phone ?? '',
            email: tenant?.user?.email ?? '',
            send_invite: false,
            id_card_number: tenant?.id_card_number ?? '',
            emergency_contact_phone: tenant?.emergency_contact_phone ?? '',
            emergency_contact_name: tenant?.emergency_contact_name ?? '',
            notes: tenant?.notes ?? '',
            is_active: tenant?.is_active ?? true,
        });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        transform((d) => ({
            ...d,
            is_active: d.is_active ? '1' : '0',
            send_invite: d.send_invite ? '1' : '0',
        }));
        submit(isEdit ? tenants.update(tenant!.id) : tenants.store(), {
            onSuccess: () => handleOpenChange(false),
        });
    }

    return (
        <Sheet
            key={tenant?.id ?? 'new'}
            open={open}
            onOpenChange={handleOpenChange}
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

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
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
                                onChange={(v) => setData('phone', v)}
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
                                disabled={emailLocked}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="e.g. tenant@example.com"
                            />
                            <p className="text-xs text-muted-foreground">
                                {emailLocked
                                    ? 'This tenant signs in with this email. To change it, disable their app access first, then edit.'
                                    : 'Used for app access and notifications. Optional.'}
                            </p>
                            <InputError message={errors.email} />
                        </div>

                        {!emailLocked && data.email !== '' && (
                            <div className="flex items-start justify-between gap-4">
                                <div className="grid gap-1">
                                    <Label htmlFor="send_invite">
                                        Send activation invite
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Emails a link to set a password and access
                                        the portal. Leave off to only save the email
                                        for notifications.
                                    </p>
                                </div>
                                <Switch
                                    id="send_invite"
                                    checked={data.send_invite}
                                    onCheckedChange={(v) =>
                                        setData('send_invite', v)
                                    }
                                />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="id_card_number">
                                ID Card Number (KTP)
                            </Label>
                            <Input
                                id="id_card_number"
                                value={data.id_card_number}
                                onChange={(e) =>
                                    setData('id_card_number', e.target.value)
                                }
                                placeholder="e.g. 3273010203040005"
                            />
                            <InputError message={errors.id_card_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="emergency_contact_phone">
                                Emergency Contact Phone
                            </Label>
                            <PhoneInput
                                value={data.emergency_contact_phone}
                                onChange={(v) =>
                                    setData('emergency_contact_phone', v)
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
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button disabled={processing}>
                            {isEdit ? 'Save' : 'Create'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
