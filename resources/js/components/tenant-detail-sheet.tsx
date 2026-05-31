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
import tenants from '@/routes/tenants';

type Property = {
    id: number;
    name: string;
};

type Room = {
    id: number;
    name: string;
    floor: string | null;
    property: Property | null;
};

type Lease = {
    id: number;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    room: Room | null;
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
    leases: Lease[];
};

function formatPrice(cents: string): string {
    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function TenantDetailSheet({
    tenant,
    open,
    onOpenChange,
    onEdit,
    onAssignToRoom,
    onMoveOut,
}: {
    tenant?: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onAssignToRoom?: () => void;
    onMoveOut?: () => void;
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
            <SheetContent className="sm:max-w-lg">
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

                            {/* Active Lease Card */}
                            <div className="rounded-lg border bg-muted/30 p-4">
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Current Lease
                                </p>
                                {activeLease ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                {activeLease.room?.name ??
                                                    'Unknown Room'}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {activeLease.room?.property
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
                                                    activeLease.monthly_rent,
                                                )}
                                                /mo
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No active lease
                                    </p>
                                )}
                            </div>

                            {/* Contact Information */}
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
                                    {!activeLease && onAssignToRoom && (
                                        <Button onClick={onAssignToRoom}>
                                            Assign to Room
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
