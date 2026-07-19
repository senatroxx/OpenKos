import { router } from '@inertiajs/react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
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
import tenants from '@/routes/tenants';

export default function InviteToAppSheet({
    tenantId,
    open,
    onOpenChange,
}: {
    tenantId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [email, setEmail] = useState('');
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    const [sendInvite, setSendInvite] = useState(true);

    function close(next: boolean) {
        if (!next) {
            setEmail('');
            setError('');
            setSending(false);
            setSendInvite(true);
        }

        onOpenChange(next);
    }

    function handleSend() {
        if (tenantId === null) {
            return;
        }

        if (!email) {
            setError('Email is required');

            return;
        }

        setSending(true);
        router.post(
            tenants.invite(tenantId).url,
            { email, send_invite: sendInvite },
            {
                onSuccess: () => close(false),
                onError: (errors) => {
                    setError(errors.email ?? 'Failed to send invitation');
                    setSending(false);
                },
            },
        );
    }

    return (
        <Sheet open={open} onOpenChange={close}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Invite to App</SheetTitle>
                    <SheetDescription>
                        Add an email to send this tenant notifications. Optionally
                        invite them to activate a portal account for payments,
                        invoices, and lease details.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="invite-email">Email</Label>
                            <Input
                                id="invite-email"
                                type="email"
                                value={email}
                                onChange={(e) => {
                                    setEmail(e.target.value);
                                    setError('');
                                }}
                                placeholder="e.g. tenant@example.com"
                            />
                            <InputError message={error} />
                        </div>

                        <div className="flex items-start justify-between gap-4">
                            <div className="grid gap-1">
                                <Label htmlFor="invite-send">
                                    Send activation invite
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Emails the tenant a link to set a password and
                                    access the portal. Leave off to only save the
                                    email for notifications.
                                </p>
                            </div>
                            <Switch
                                id="invite-send"
                                checked={sendInvite}
                                onCheckedChange={setSendInvite}
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => close(false)}
                            disabled={sending}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={sending || !email}
                            onClick={handleSend}
                        >
                            {sending
                                ? 'Saving...'
                                : sendInvite
                                  ? 'Send Invitation'
                                  : 'Save Email'}
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
