import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate, formatPrice } from '@/lib/formatters';
import tenants from '@/routes/tenants';
import type { UnitWithProperty, TenantInfo } from '@/types';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    unit: UnitWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
    deleted_at: string | null;
    active_leases_count: number;
    leases?: Lease[];
    documents?: {
        id: number;
        type: string;
        original_name: string;
        size: number;
        mime_type: string;
        created_at: string;
        download_url: string;
    }[];
};

export default function TenantDetailSheet({
    tenant,
    open,
    onOpenChange,
    onEdit,
    onAssignToUnit,
    onMoveOut,
    onDocuments,
}: {
    tenant?: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onAssignToUnit?: () => void;
    onMoveOut?: () => void;
    onDocuments?: () => void;
}) {
    function archive() {
        if (!tenant) {
            return;
        }

        if (confirm('Are you sure you want to archive this tenant?')) {
            router.delete(tenants.destroy.url(tenant), {
                onSuccess: () => onOpenChange(false),
            });
        }
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
                    <SheetDescription>Tenant details</SheetDescription>
                </SheetHeader>

                {tenant && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-5">
                            <div className="flex items-center gap-2">
                                <span>Status:</span>
                                {isArchived ? (
                                    <Badge variant="secondary">Archived</Badge>
                                ) : tenant.is_active ? (
                                    <Badge className="bg-green-600">
                                        Active
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-300 text-amber-600"
                                    >
                                        Inactive
                                    </Badge>
                                )}
                            </div>

                            <div className="rounded-lg border bg-muted/30 p-4">
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Current Lease
                                </p>
                                {activeLease ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                {activeLease.unit?.name ??
                                                    'Unknown Unit'}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {activeLease.reference}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">
                                                {activeLease.unit?.property
                                                    ?.name ??
                                                    'Unknown Property'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {formatDate(
                                                    activeLease.start_date,
                                                )}
                                                {activeLease.end_date
                                                    ? ` — ${formatDate(activeLease.end_date)}`
                                                    : ' — Present'}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    activeLease.rent_amount,
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
                                                            Co-tenants
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
                                                            Main tenant
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
                                        No active lease
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="mt-1 text-sm">
                                    {tenant.phone ?? '—'}
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    ID Card (KTP)
                                </p>
                                <p className="mt-1 text-sm tabular-nums">
                                    {tenant.id_card_number ?? '—'}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Emergency Contact
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {tenant.emergency_contact_name ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Emergency Phone
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">
                                        {tenant.emergency_contact_phone ?? '—'}
                                    </p>
                                </div>
                            </div>

                            {tenant.notes && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Notes
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {tenant.notes}
                                    </p>
                                </div>
                            )}

                            {!isArchived && (
                                <Button
                                    variant="outline"
                                    onClick={onDocuments}
                                    className="w-full"
                                >
                                    Documents
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center justify-end gap-4">
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Close
                            </Button>
                            {!isArchived && (
                                <>
                                    {!activeLease && onAssignToUnit && (
                                        <Button onClick={onAssignToUnit}>
                                            Assign to Unit
                                        </Button>
                                    )}
                                    {activeLease && onMoveOut && (
                                        <Button
                                            variant="destructive"
                                            onClick={onMoveOut}
                                        >
                                            Move Out
                                        </Button>
                                    )}
                                    <Button variant="outline" onClick={archive}>
                                        Archive
                                    </Button>
                                    <Button
                                        onClick={() => {
                                            onOpenChange(false);
                                            onEdit();
                                        }}
                                    >
                                        Edit
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
