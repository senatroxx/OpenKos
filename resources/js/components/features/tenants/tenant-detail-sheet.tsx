import { router } from '@inertiajs/react';
import { MailPlus, Send, UserX } from 'lucide-react';
import { useState } from 'react';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { appAccessStatus, inviteActionLabel } from '@/lib/app-access';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import tenants from '@/routes/tenants';
import type { Lease, TenantDocument, WorkspaceTenant } from '@/types';

export default function TenantDetailSheet({
    tenant,
    open,
    onOpenChange,
    onEdit,
    onDocuments,
    onAssignToUnit,
    onMoveOut,
    onInvite,
    onResend,
    onDisableAccess,
}: {
    tenant?:
        | (WorkspaceTenant & { leases?: Lease[]; documents?: TenantDocument[] })
        | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onDocuments: () => void;
    onAssignToUnit?: () => void;
    onMoveOut?: () => void;
    onInvite?: () => void;
    onResend?: () => void;
    onDisableAccess?: () => void;
}) {
    const [archiveConfirm, setArchiveConfirm] = useState(false);

    function archive() {
        if (!tenant) {
            return;
        }

        setArchiveConfirm(true);
    }

    function confirmArchive() {
        if (!tenant) {
            return;
        }

        router.delete(tenants.destroy.url(tenant), {
            onSuccess: () => onOpenChange(false),
        });
        setArchiveConfirm(false);
    }

    const activeLease = tenant?.leases?.[0];
    const isArchived = Boolean(tenant?.deleted_at);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="sm:max-w-lg"
                expandTo={tenant ? tenants.show.url(tenant) : undefined}
            >
                <SheetHeader>
                    <SheetTitle>{tenant?.name}</SheetTitle>
                    <SheetDescription>{t('Tenant details')}</SheetDescription>
                </SheetHeader>

                {tenant && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span>{t('Status:')}</span>
                                {(() => {
                                    const status = isArchived
                                        ? 'archived'
                                        : tenant.is_active
                                          ? 'active'
                                          : 'inactive';

                                    return (
                                        <StatusBadge
                                            domain="tenant"
                                            value={status}
                                        />
                                    );
                                })()}
                                <span className="ml-2">{t('App access:')}</span>
                                <StatusBadge
                                    domain="app_access"
                                    value={appAccessStatus(tenant.user)}
                                />
                            </div>

                            <div className="rounded-lg border bg-muted/30 p-4">
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    {t('Current Lease')}
                                </p>
                                {activeLease ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                {activeLease.unit?.name ??
                                                    t('Unknown Unit')}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {activeLease.reference}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">
                                                {activeLease.unit?.property
                                                    ?.name ??
                                                    t('Unknown Property')}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {formatDate(
                                                    activeLease.start_date,
                                                )}
                                                {activeLease.end_date
                                                    ? ` — ${formatDate(activeLease.end_date)}`
                                                    : ` — ${t('Present')}`}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    activeLease.rent_amount,
                                                    activeLease.currency,
                                                )}
                                                /mo
                                            </span>
                                        </div>
                                        {(activeLease.tenants ?? []).length >
                                            1 && (
                                            <div className="border-t pt-2">
                                                {activeLease.primary_tenant
                                                    ?.id === tenant.id ? (
                                                    <>
                                                        <p className="mb-1 text-xs text-muted-foreground">
                                                            {t('Co-tenants')}
                                                        </p>
                                                        <div className="space-y-1">
                                                            {activeLease.tenants
                                                                .filter(
                                                                    (t) =>
                                                                        !t.pivot
                                                                            ?.is_primary,
                                                                )
                                                                .map((t) => (
                                                                    <div
                                                                        key={
                                                                            t.id
                                                                        }
                                                                        className="flex items-center justify-between text-sm"
                                                                    >
                                                                        <span>
                                                                            {
                                                                                t.name
                                                                            }
                                                                        </span>
                                                                        {t.phone && (
                                                                            <span className="text-xs text-muted-foreground">
                                                                                {
                                                                                    t.phone
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                ))}
                                                        </div>
                                                    </>
                                                ) : activeLease.primary_tenant ? (
                                                    <>
                                                        <p className="mb-1 text-xs text-muted-foreground">
                                                            {t('Main tenant')}
                                                        </p>
                                                        <div className="flex items-center justify-between text-sm">
                                                            <span>
                                                                {
                                                                    activeLease
                                                                        .primary_tenant
                                                                        .name
                                                                }
                                                            </span>
                                                            {activeLease
                                                                .primary_tenant
                                                                .phone && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {
                                                                        activeLease
                                                                            .primary_tenant
                                                                            .phone
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    </>
                                                ) : null}
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('No active lease')}
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    {t('Phone')}
                                </p>
                                <p className="mt-1 text-sm">
                                    {tenant.phone ?? '—'}
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    {t('ID Card (KTP)')}
                                </p>
                                <p className="mt-1 text-sm tabular-nums">
                                    {tenant.id_card_number ?? '—'}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        {t('Emergency Contact')}
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {tenant.emergency_contact_name ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        {t('Emergency Phone')}
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">
                                        {tenant.emergency_contact_phone ?? '—'}
                                    </p>
                                </div>
                            </div>

                            {tenant.notes && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        {t('Notes')}
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {tenant.notes}
                                    </p>
                                </div>
                            )}

                            {!isArchived && tenant && (
                                <Button
                                    variant="outline"
                                    onClick={onDocuments}
                                    className="w-full"
                                >
                                    {t('Documents')}
                                </Button>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4">
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                {t('Close')}
                            </Button>
                            {!isArchived && tenant && (
                                <>
                                    {!tenant.user_id && onInvite && (
                                        <Button
                                            variant="outline"
                                            onClick={onInvite}
                                        >
                                            <MailPlus className="size-4" />
                                            {t('Invite to App')}
                                        </Button>
                                    )}
                                    {inviteActionLabel(
                                        appAccessStatus(tenant.user),
                                    ) &&
                                        onResend && (
                                            <Button
                                                variant="outline"
                                                onClick={onResend}
                                            >
                                                <Send className="size-4" />
                                                {inviteActionLabel(
                                                    appAccessStatus(
                                                        tenant.user,
                                                    ),
                                                )}
                                            </Button>
                                        )}
                                    {['invited', 'active'].includes(
                                        appAccessStatus(tenant.user),
                                    ) &&
                                        onDisableAccess && (
                                            <Button
                                                variant="outline"
                                                onClick={onDisableAccess}
                                            >
                                                <UserX className="size-4" />
                                                {t('Disable Access')}
                                            </Button>
                                        )}
                                    {!activeLease && onAssignToUnit && (
                                        <Button onClick={onAssignToUnit}>
                                            {t('Assign to Unit')}
                                        </Button>
                                    )}
                                    {activeLease && onMoveOut && (
                                        <Button
                                            variant="destructive"
                                            onClick={onMoveOut}
                                        >
                                            {t('Move Out')}
                                        </Button>
                                    )}
                                    <Button variant="outline" onClick={archive}>
                                        {t('Archive')}
                                    </Button>
                                    <Button onClick={onEdit}>
                                        {t('Edit')}
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </SheetContent>

            <Dialog open={archiveConfirm} onOpenChange={setArchiveConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Archive tenant')}</DialogTitle>
                        <DialogDescription>
                            {t('Are you sure you want to archive')}{' '}
                            <span className="font-medium">{tenant?.name}</span>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveConfirm(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmArchive}>
                            {t('Archive')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Sheet>
    );
}
