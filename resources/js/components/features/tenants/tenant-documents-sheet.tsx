import { router } from '@inertiajs/react';
import { FileDown, Trash2, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { DocumentPreview } from '@/components/shared';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate, formatSize } from '@/lib/formatters';
import tenants from '@/routes/tenants';
import type { Tenant, TenantDocument } from '@/types';

const TYPE_LABELS: Record<string, string> = {
    ktp: 'KTP',
    passport: 'Passport',
    lease: 'Lease/Agreement',
    supporting: 'Supporting',
    other: 'Other',
};

export default function TenantDocumentsSheet({
    tenant,
    open,
    onOpenChange,
}: {
    tenant?: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [uploading, setUploading] = useState(false);
    const [docType, setDocType] = useState('');
    const [previewDoc, setPreviewDoc] = useState<TenantDocument | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<TenantDocument | null>(
        null,
    );

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
                setDocType('');
                form.reset();
            },
        });
    }

    function handleDelete(document: TenantDocument) {
        setDeleteConfirm(document);
    }

    function confirmDelete() {
        if (!tenant || !deleteConfirm) {
            return;
        }

        router.delete(
            tenants.documents.destroy.url({
                tenant: tenant.id,
                document: deleteConfirm.id,
            }),
            { preserveState: true, replace: true },
        );
        setDeleteConfirm(null);
    }

    function docUrl(doc: TenantDocument): string | null {
        if (!tenant) {
            return null;
        }

        return tenants.documents.show.url({
            tenant: tenant.id,
            document: doc.id,
        });
    }

    function handlePreview(doc: TenantDocument) {
        if (
            doc.mime_type.startsWith('image/') ||
            doc.mime_type === 'application/pdf'
        ) {
            setPreviewDoc(doc);
        }
    }

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Documents</SheetTitle>
                        <SheetDescription>
                            {tenant?.name ?? 'Tenant'}
                        </SheetDescription>
                    </SheetHeader>

                    {tenant && (
                        <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    {(tenant.documents ?? []).map((doc) => (
                                        <div
                                            key={doc.id}
                                            className="flex items-center justify-between gap-2 rounded-md border bg-muted/20 px-3 py-2"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handlePreview(doc)
                                                }
                                                className={
                                                    'min-w-0 flex-1 truncate text-left text-xs text-muted-foreground' +
                                                    (doc.mime_type.startsWith(
                                                        'image/',
                                                    ) ||
                                                    doc.mime_type ===
                                                        'application/pdf'
                                                        ? ' cursor-pointer hover:text-foreground'
                                                        : '')
                                                }
                                            >
                                                {TYPE_LABELS[doc.type] ??
                                                    doc.type}
                                                {' · '}
                                                {formatSize(doc.size)}
                                                {' · '}
                                                {formatDate(doc.created_at)}
                                            </button>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <a
                                                    href={docUrl(doc) ?? '#'}
                                                    download={doc.original_name}
                                                    className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                                                >
                                                    <FileDown className="size-4" />
                                                </a>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(doc)
                                                    }
                                                    className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {(tenant.documents ?? []).length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No documents
                                    </p>
                                )}

                                <form
                                    onSubmit={handleUpload}
                                    className="flex flex-row items-end gap-2"
                                >
                                    <input
                                        type="hidden"
                                        name="type"
                                        value={docType}
                                    />
                                    <Select
                                        value={docType}
                                        onValueChange={setDocType}
                                    >
                                        <SelectTrigger className="w-[160px] shrink-0">
                                            <SelectValue placeholder="Select type..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ktp">
                                                KTP
                                            </SelectItem>
                                            <SelectItem value="passport">
                                                Passport
                                            </SelectItem>
                                            <SelectItem value="lease">
                                                Lease/Agreement
                                            </SelectItem>
                                            <SelectItem value="supporting">
                                                Supporting
                                            </SelectItem>
                                            <SelectItem value="other">
                                                Other
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="file"
                                        name="file"
                                        required
                                        className="w-full file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
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
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={deleteConfirm !== null}
                onOpenChange={() => setDeleteConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete document</DialogTitle>
                        <DialogDescription>
                            Delete this document? This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {previewDoc && tenant && (
                <DocumentPreview
                    src={docUrl(previewDoc)!}
                    mimeType={previewDoc.mime_type}
                    title={TYPE_LABELS[previewDoc.type] ?? previewDoc.type}
                    subtitle={`${formatSize(previewDoc.size)} · ${formatDate(previewDoc.created_at)}`}
                    onClose={() => setPreviewDoc(null)}
                />
            )}
        </>
    );
}
