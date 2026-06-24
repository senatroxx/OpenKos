import { router } from '@inertiajs/react';
import { FileDown, Trash2, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
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

type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
    pivot?: { is_primary: boolean };
};

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
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type TenantDocument = {
    id: number;
    type: string;
    original_name: string;
    size: number;
    created_at: string;
    download_url: string;
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
    documents: TenantDocument[];
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

function formatSize(bytes: number): string {
    if (bytes < 1024) {
return bytes + ' B';
}

    if (bytes < 1024 * 1024) {
return (bytes / 1024).toFixed(0) + ' KB';
}

    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

const TYPE_LABELS: Record<string, string> = {
    ktp: 'KTP',
    passport: 'Passport',
    lease: 'Lease/Agreement',
    supporting: 'Supporting',
    other: 'Other',
};

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
    const [uploading, setUploading] = useState(false);

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

    function handleUpload(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();

        if (!tenant) {
            return;
        }

        const form = e.currentTarget;
        const formData = new FormData(form);

        setUploading(true);
        router.post(tenants.documents.store.url(tenant), formData, {
            preserveState: true,
            replace: true,
            onFinish: () => {
                setUploading(false);
                form.reset();
            },
        });
    }

    function handleDelete(document: TenantDocument) {
        if (!tenant || !confirm('Delete this document?')) {
            return;
        }

        router.delete(
            tenants.documents.destroy.url({
                tenant: tenant.id,
                document: document.id,
            }),
            { preserveState: true, replace: true },
        );
    }

    const activeLease = tenant?.leases?.[0];
    const isArchived = Boolean(tenant?.deleted_at);
    const isOwnerOrStaff = true;

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
                                        {(activeLease.tenants ?? []).length > 1 && (
                                            <div className="border-t pt-2">
                                                <p className="mb-1 text-xs text-muted-foreground">
                                                    Co-tenants
                                                </p>
                                                <div className="space-y-1">
                                                    {activeLease.tenants
                                                        .filter((t) => !t.pivot?.is_primary)
                                                        .map((t) => (
                                                            <div key={t.id} className="flex items-center justify-between text-sm">
                                                                <span>{t.name}</span>
                                                                {t.phone && (
                                                                    <span className="text-xs text-muted-foreground">{t.phone}</span>
                                                                )}
                                                            </div>
                                                        ))}
                                                </div>
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
                                <div className="rounded-lg border p-4">
                                    <p className="mb-3 text-xs font-medium text-muted-foreground uppercase">
                                        Documents
                                    </p>

                                    <div className="space-y-2">
                                        {(tenant.documents ?? []).map((doc) => (
                                            <div
                                                key={doc.id}
                                                className="flex items-center justify-between gap-2 rounded-md border bg-muted/20 px-3 py-2"
                                            >
                                                <p className="min-w-0 flex-1 text-xs text-muted-foreground">
                                                    {TYPE_LABELS[doc.type] ??
                                                        doc.type}
                                                    {' · '}
                                                    {formatSize(doc.size)}
                                                    {' · '}
                                                    {formatDate(
                                                        doc.created_at,
                                                    )}
                                                </p>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <a
                                                        href={tenants.documents.show.url({ tenant: tenant.id, document: doc.id })}
                                                        download={doc.original_name}
                                                        className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                                                    >
                                                        <FileDown className="size-4" />
                                                    </a>
                                                    {isOwnerOrStaff && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleDelete(doc)
                                                            }
                                                            className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {(tenant.documents ?? []).length === 0 && (
                                        <p className="mb-3 text-sm text-muted-foreground">
                                            No documents
                                        </p>
                                    )}

                                    <form
                                        onSubmit={handleUpload}
                                        className="mt-3 flex items-center gap-2"
                                    >
                                        <select
                                            name="type"
                                            required
                                            className="block w-40 shrink-0 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <option value="">
                                                Select type...
                                            </option>
                                            <option value="ktp">KTP</option>
                                            <option value="passport">
                                                Passport
                                            </option>
                                            <option value="lease">
                                                Lease/Agreement
                                            </option>
                                            <option value="supporting">
                                                Supporting
                                            </option>
                                            <option value="other">Other</option>
                                        </select>
                                        <input
                                            type="file"
                                            name="file"
                                            required
                                            className="min-w-0 flex-1 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={uploading}
                                            className="shrink-0"
                                        >
                                            <Upload className="size-4" />
                                        </Button>
                                    </form>
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
