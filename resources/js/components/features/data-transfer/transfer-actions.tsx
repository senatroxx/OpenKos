import { useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    EllipsisVertical,
    FileDown,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import {
    commit,
    exportMethod,
    preview,
    template,
} from '@/actions/App/Http/Controllers/DataTransferController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { QueryParams } from '@/wayfinder';

export const TRANSFER_MAX_ROWS = 10_000;
export const TRANSFER_MAX_FILE_SIZE_MB = 10;

type TransferError = {
    line: number | null;
    field: string;
    message: string;
};

type TransferResponse = {
    valid?: boolean;
    row_count?: number;
    error_count?: number;
    errors?: TransferError[];
    count?: number;
};

type ImportForm = {
    dataset: string;
    file: File | null;
};

export type TransferActionQuery = QueryParams;

function normalizeErrors(value: unknown): TransferError[] {
    if (Array.isArray(value)) {
        return value.filter(
            (error): error is TransferError =>
                typeof error === 'object' &&
                error !== null &&
                typeof error.field === 'string' &&
                typeof error.message === 'string',
        );
    }

    if (typeof value !== 'object' || value === null) {
        return [];
    }

    return Object.entries(value).flatMap(([field, messages]) =>
        (Array.isArray(messages) ? messages : [messages]).map((message) => ({
            line: null,
            field,
            message: String(message),
        })),
    );
}

function transferError(message: string): TransferError[] {
    return [{ line: null, field: 'file', message }];
}

export function TransferImportDialog({
    dataset,
    datasetLabel,
    open,
    onOpenChange,
    maxRows = TRANSFER_MAX_ROWS,
    maxFileSizeMb = TRANSFER_MAX_FILE_SIZE_MB,
}: {
    dataset: string;
    datasetLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    maxRows?: number;
    maxFileSizeMb?: number;
}) {
    const [fileInputKey, setFileInputKey] = useState(0);
    const [previewResult, setPreviewResult] = useState<TransferResponse | null>(
        null,
    );
    const [errors, setErrors] = useState<TransferError[]>([]);
    const [committedCount, setCommittedCount] = useState<number | null>(null);
    const request = useHttp<ImportForm, TransferResponse>({
        dataset,
        file: null,
    });

    function reset() {
        request.setData('file', null);
        setPreviewResult(null);
        setErrors([]);
        setCommittedCount(null);
        setFileInputKey((key) => key + 1);
    }

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen) {
            reset();
        }

        onOpenChange(nextOpen);
    }

    function selectFile(file: File | null) {
        request.setData('file', file);
        setPreviewResult(null);
        setErrors([]);
        setCommittedCount(null);
    }

    function previewFile() {
        setErrors([]);
        setPreviewResult(null);
        setCommittedCount(null);

        request.withAllErrors().post(preview().url, {
            onSuccess: (response) => {
                setPreviewResult(response);
                setErrors(response.errors ?? []);
            },
            onError: (validationErrors) => {
                setErrors(normalizeErrors(validationErrors));
            },
            onHttpException: () => {
                setErrors(
                    transferError(t('The preview could not be completed.')),
                );
            },
            onNetworkError: () => {
                setErrors(
                    transferError(t('The preview could not be completed.')),
                );
            },
        });
    }

    function commitFile() {
        setErrors([]);
        setCommittedCount(null);

        request.withAllErrors().post(commit().url, {
            onSuccess: (response) => {
                setCommittedCount(response.count ?? 0);
                setPreviewResult(null);
            },
            onError: (validationErrors) => {
                setErrors(normalizeErrors(validationErrors));
                setPreviewResult(null);
            },
            onHttpException: () => {
                setErrors(
                    transferError(t('The import could not be committed.')),
                );
            },
            onNetworkError: () => {
                setErrors(
                    transferError(t('The import could not be committed.')),
                );
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('Import :dataset', { dataset: datasetLabel })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Upload a versioned CSV, validate every row, then create all records atomically.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor={`${dataset}-csv-file`}>
                            {t('CSV file')}
                        </Label>
                        <Input
                            key={fileInputKey}
                            id={`${dataset}-csv-file`}
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(event) =>
                                selectFile(event.target.files?.[0] ?? null)
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('CSV only, up to :size MB and :rows rows.', {
                                size: maxFileSizeMb,
                                rows: maxRows,
                            })}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={previewFile}
                            disabled={!request.data.file || request.processing}
                        >
                            <Upload />
                            {request.processing
                                ? t('Checking...')
                                : t('Preview and validate')}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <a href={template(dataset).url}>
                                <FileDown />
                                {t('Download :dataset template', {
                                    dataset: datasetLabel,
                                })}
                            </a>
                        </Button>
                    </div>

                    {(previewResult || errors.length > 0) && (
                        <div className="grid gap-4 border-t pt-6">
                            <div className="grid gap-1.5">
                                <h3 className="leading-none font-semibold">
                                    {t('Validation result')}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {previewResult
                                        ? t(
                                              ':rows data rows checked, :errors errors found.',
                                              {
                                                  rows:
                                                      previewResult.row_count ??
                                                      0,
                                                  errors:
                                                      previewResult.error_count ??
                                                      0,
                                              },
                                          )
                                        : t('The file could not be validated.')}
                                </p>
                            </div>

                            {errors.length > 0 && (
                                <div className="overflow-x-auto rounded-md border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2 font-medium">
                                                    {t('Line')}
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    {t('Field')}
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    {t('Error')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {errors.map((error, index) => (
                                                <tr
                                                    key={`${error.line ?? 'file'}-${error.field}-${index}`}
                                                    className="border-t"
                                                >
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {error.line ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2 font-medium">
                                                        {error.field}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {error.message}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {committedCount !== null && (
                        <Alert>
                            <CheckCircle2 />
                            <AlertTitle>{t('Import committed')}</AlertTitle>
                            <AlertDescription>
                                {t(':count new records were created.', {
                                    count: committedCount,
                                })}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        onClick={commitFile}
                        disabled={
                            previewResult?.valid !== true || request.processing
                        }
                    >
                        {t('Import :dataset', { dataset: datasetLabel })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function TransferExportDialog({
    dataset,
    datasetLabel,
    open,
    onOpenChange,
    query = {},
    includeArchivedDefault = false,
    canExportSensitive = false,
}: {
    dataset: string;
    datasetLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    query?: TransferActionQuery;
    includeArchivedDefault?: boolean;
    canExportSensitive?: boolean;
}) {
    const [includeArchived, setIncludeArchived] = useState(
        includeArchivedDefault,
    );

    const exportQuery = {
        ...query,
        include_archived: includeArchived ? 1 : undefined,
    } satisfies TransferActionQuery;

    function exportUrl(includeSensitive = false): string {
        return exportMethod.url(dataset, {
            query: {
                ...exportQuery,
                ...(includeSensitive ? { include_sensitive: 1 } : {}),
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {t('Export :dataset', { dataset: datasetLabel })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Export the current list context in the stable versioned CSV format. Active records are included by default.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={includeArchived}
                        onChange={(event) =>
                            setIncludeArchived(event.target.checked)
                        }
                        className="size-4 rounded border-input"
                    />
                    {t('Include archived/inactive')}
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    {canExportSensitive && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl(true)}>
                                <Download />
                                {t('Export :dataset with sensitive fields', {
                                    dataset: datasetLabel,
                                })}
                            </a>
                        </Button>
                    )}
                    <Button asChild>
                        <a href={exportUrl()}>
                            <Download />
                            {t('Export :dataset', { dataset: datasetLabel })}
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function EntityTransferMenu({
    dataset,
    datasetLabel,
    canImport = false,
    canExport = false,
    exportQuery,
    includeArchivedDefault = false,
    canExportSensitive = false,
}: {
    dataset: string;
    datasetLabel: string;
    canImport?: boolean;
    canExport?: boolean;
    exportQuery?: TransferActionQuery;
    includeArchivedDefault?: boolean;
    canExportSensitive?: boolean;
}) {
    const [importOpen, setImportOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);

    if (!canImport && !canExport) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={t('Import or export :dataset', {
                            dataset: datasetLabel,
                        })}
                    >
                        <EllipsisVertical />
                        <span className="sr-only">
                            {t('Import or export :dataset', {
                                dataset: datasetLabel,
                            })}
                        </span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {canImport && (
                        <DropdownMenuItem onSelect={() => setImportOpen(true)}>
                            <Upload />
                            {t('Import :dataset', { dataset: datasetLabel })}
                        </DropdownMenuItem>
                    )}
                    {canImport && canExport && <DropdownMenuSeparator />}
                    {canExport && (
                        <DropdownMenuItem onSelect={() => setExportOpen(true)}>
                            <Download />
                            {t('Export :dataset', { dataset: datasetLabel })}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {canImport && (
                <TransferImportDialog
                    dataset={dataset}
                    datasetLabel={datasetLabel}
                    open={importOpen}
                    onOpenChange={setImportOpen}
                />
            )}
            {canExport && (
                <TransferExportDialog
                    key={`${dataset}-${includeArchivedDefault ? 'archived' : 'active'}`}
                    dataset={dataset}
                    datasetLabel={datasetLabel}
                    open={exportOpen}
                    onOpenChange={setExportOpen}
                    query={exportQuery}
                    includeArchivedDefault={includeArchivedDefault}
                    canExportSensitive={canExportSensitive}
                />
            )}
        </>
    );
}
