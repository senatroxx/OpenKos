import { router } from '@inertiajs/react';
import { useState } from 'react';
import InviteToAppSheet from '@/components/features/tenants/invite-to-app-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
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

    const user = tenant.user;
    const isActivated = user?.is_active && user?.email_verified_at;
    const isInvited = user && !isActivated && user.invited_at;
    const isEmailOnly = user && !isActivated && !user.invited_at;

    function handleResend() {
        router.post(tenants.resendInvitation(tenant.id).url);
    }

    function handleDisable() {
        router.post(tenants.disableAccess(tenant.id).url);
    }

    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                App Access
            </p>

            {!user && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Not activated
                        </span>
                        <StatusBadge domain="user" value="disabled" />
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

            {isEmailOnly && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="user" value="notified" />
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

            {isInvited && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="user" value="invited" />
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
                            onClick={handleDisable}
                        >
                            Disable Access
                        </Button>
                    </div>
                </div>
            )}

            {isActivated && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                        <StatusBadge domain="user" value="active" />
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
                            onClick={handleDisable}
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
        </div>
    );
}
