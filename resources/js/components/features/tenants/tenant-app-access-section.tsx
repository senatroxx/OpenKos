import { router } from '@inertiajs/react';
import { useState } from 'react';
import InviteToAppSheet from '@/components/features/tenants/invite-to-app-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { appAccessStatus } from '@/lib/app-access';
import { formatDate } from '@/lib/formatters';
import tenants from '@/routes/tenants';
import type { Tenant } from '@/types';

type TenantWithUser = Tenant & {
    user?: {
        id: number;
        email: string;
        email_verified_at: string | null;
        last_login_at: string | null;
        is_active: boolean;
        invited_at: string | null;
    } | null;
};

export default function TenantAppAccessSection({
    tenant,
}: {
    tenant: TenantWithUser;
}) {
    const [inviteOpen, setInviteOpen] = useState(false);
    const [disableConfirm, setDisableConfirm] = useState(false);

    const user = tenant.user;
    const status = appAccessStatus(user);

    function handleResend() {
        router.post(tenants.resendInvitation(tenant.id).url);
    }

    function confirmDisable() {
        router.post(tenants.disableAccess(tenant.id).url);
        setDisableConfirm(false);
    }

    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                App Access
            </p>

            {status === 'none' && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Not activated
                        </span>
                        <StatusBadge domain="app_access" value="none" />
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setInviteOpen(true)}
                    >
                        Invite to App
                    </Button>
                </div>
            )}

            {status === 'email_only' && user && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="app_access" value="email_only" />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Receives notifications. No portal access yet.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleResend}
                    >
                        Send Invitation
                    </Button>
                </div>
            )}

            {status === 'invited' && user && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="app_access" value="invited" />
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResend}
                        >
                            Resend Invitation
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setDisableConfirm(true)}
                        >
                            Disable Access
                        </Button>
                    </div>
                </div>
            )}

            {status === 'disabled' && user && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="app_access" value="disabled" />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Access disabled. Still receives notifications.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleResend}
                    >
                        Re-invite
                    </Button>
                </div>
            )}

            {status === 'active' && user && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="app_access" value="active" />
                    </div>
                    {user.last_login_at && (
                        <p className="text-xs text-muted-foreground">
                            Last login: {formatDate(user.last_login_at)}
                        </p>
                    )}
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResend}
                        >
                            Resend Invitation
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setDisableConfirm(true)}
                        >
                            Disable Access
                        </Button>
                    </div>
                </div>
            )}

            <InviteToAppSheet
                tenantId={tenant.id}
                open={inviteOpen}
                onOpenChange={setInviteOpen}
            />

            <Dialog open={disableConfirm} onOpenChange={setDisableConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Disable app access</DialogTitle>
                        <DialogDescription>
                            This signs{' '}
                            <span className="font-medium">{tenant.name}</span>{' '}
                            out and revokes their portal access. They'll still
                            receive notifications, and you can re-invite them
                            later.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDisableConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDisable}>
                            Disable Access
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
