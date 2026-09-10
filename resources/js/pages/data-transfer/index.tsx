import { Head, useHttp, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, FileDown, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    commit,
    exportMethod,
    index,
    preview,
    template,
} from '@/actions/App/Http/Controllers/DataTransferController';
import { Heading } from '@/components/shared';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { Auth } from '@/types';

type Dataset = {
    value: string;
    label: string;
    importable: boolean;
    exportable: boolean;
};

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
    committed?: boolean;
    count?: number;
};

type ImportForm = {
    dataset: string;
    file: File | null;
};

type PageProps = {
    datasets: Dataset[];
    maxRows: number;
    maxFileSizeMb: number;
};

function normalizeErrors(value: unknown): TransferError[] {
    if (Array.isArray(value)) {
        return value.filter(
            (error): error is TransferError =>
                typeof error === 'object' &&
                error !== null &&
                'field' in error &&
                'message' in error,
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

export default function Index({ datasets, maxRows, maxFileSizeMb }: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const firstDataset = datasets[0];
    const [selectedDataset, setSelectedDataset] = useState(
        firstDataset?.value ?? '',
    );
    const [previewResult, setPreviewResult] = useState<TransferResponse | null>(
        null,
    );
    const [errors, setErrors] = useState<TransferError[]>([]);
    const [includeArchived, setIncludeArchived] = useState(false);
    const [committedCount, setCommittedCount] = useState<number | null>(null);
    const request = useHttp<ImportForm, TransferResponse>({
        dataset: selectedDataset,
        file: null,
    });

    const selected = useMemo(
        () => datasets.find((dataset) => dataset.value === selectedDataset),
        [datasets, selectedDataset],
    );
    const selectedDatasetLabel = selected?.label ?? t('Dataset');
    const canExportSensitiveTenants =
        selectedDataset === 'tenants' &&
        (auth.role === 'owner' ||
            auth.permissions.includes('tenants.export_sensitive'));

    function selectDataset(value: string) {
        setSelectedDataset(value);
        request.setData('dataset', value);
        request.setData('file', null);
        setPreviewResult(null);
        setErrors([]);
        setCommittedCount(null);
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
                setErrors([
                    {
                        line: null,
                        field: 'file',
                        message: t('The preview could not be completed.'),
                    },
                ]);
            },
            onNetworkError: () => {
                setErrors([
                    {
                        line: null,
                        field: 'file',
                        message: t('The preview could not be completed.'),
                    },
                ]);
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
                setErrors([
                    {
                        line: null,
                        field: 'file',
                        message: t('The import could not be committed.'),
                    },
                ]);
            },
            onNetworkError: () => {
                setErrors([
                    {
                        line: null,
                        field: 'file',
                        message: t('The import could not be committed.'),
                    },
                ]);
            },
        });
    }

    const exportQuery = {
        include_archived: includeArchived ? 1 : undefined,
    };

    return (
        <>
            <Head title={t('Data transfer')} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={t('Data transfer')}
                    description={t(
                        'Move master data in and out with versioned CSV files.',
                    )}
                />

                <div className="grid gap-2 sm:max-w-sm">
                    <Label htmlFor="dataset">{t('Dataset')}</Label>
                    <select
                        id="dataset"
                        value={selectedDataset}
                        onChange={(event) => selectDataset(event.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        {datasets.map((dataset) => (
                            <option key={dataset.value} value={dataset.value}>
                                {dataset.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Import CSV')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Preview validates the entire file without saving. Commit creates all rows atomically.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            {selected?.importable ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="csv-file">
                                            {t('CSV file')}
                                        </Label>
                                        <Input
                                            id="csv-file"
                                            type="file"
                                            accept=".csv,text/csv"
                                            onChange={(event) =>
                                                selectFile(
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'CSV only, up to :size MB and :rows rows.',
                                                {
                                                    size: maxFileSizeMb,
                                                    rows: maxRows,
                                                },
                                            )}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            onClick={previewFile}
                                            disabled={
                                                !request.data.file ||
                                                request.processing
                                            }
                                        >
                                            <Upload />
                                            {request.processing
                                                ? t('Checking...')
                                                : t('Preview and validate')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <a
                                                href={
                                                    template(selectedDataset)
                                                        .url
                                                }
                                            >
                                                <FileDown />
                                                {t(
                                                    'Download :dataset template',
                                                    {
                                                        dataset:
                                                            selectedDatasetLabel,
                                                    },
                                                )}
                                            </a>
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <Alert>
                                    <AlertTitle>
                                        {t('Export-only dataset')}
                                    </AlertTitle>
                                    <AlertDescription>
                                        {t(
                                            'This dataset can be exported for reference but is not an import domain in v1.',
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}

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
                                                : t(
                                                      'The file could not be validated.',
                                                  )}
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
                                                    {errors.map(
                                                        (error, index) => (
                                                            <tr
                                                                key={`${error.line ?? 'file'}-${error.field}-${index}`}
                                                                className="border-t"
                                                            >
                                                                <td className="px-3 py-2 tabular-nums">
                                                                    {error.line ??
                                                                        '—'}
                                                                </td>
                                                                <td className="px-3 py-2 font-medium">
                                                                    {
                                                                        error.field
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2 text-muted-foreground">
                                                                    {
                                                                        error.message
                                                                    }
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    <Button
                                        type="button"
                                        onClick={commitFile}
                                        disabled={
                                            previewResult?.valid !== true ||
                                            request.processing
                                        }
                                    >
                                        {t('Commit import')}
                                    </Button>
                                </div>
                            )}

                            {committedCount !== null && (
                                <Alert>
                                    <CheckCircle2 />
                                    <AlertTitle>
                                        {t('Import committed')}
                                    </AlertTitle>
                                    <AlertDescription>
                                        {t(':count new records were created.', {
                                            count: committedCount,
                                        })}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>

                    {selected?.exportable && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Export CSV')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Exports use the stable v1 column format. Archived and inactive records are excluded by default.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-3">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={includeArchived}
                                        onChange={(event) =>
                                            setIncludeArchived(
                                                event.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    {t('Include archived/inactive')}
                                </label>
                                <Button variant="outline" asChild>
                                    <a
                                        href={exportMethod.url(
                                            selectedDataset,
                                            {
                                                query: exportQuery,
                                            },
                                        )}
                                    >
                                        <Download />
                                        {t('Export :dataset', {
                                            dataset: selectedDatasetLabel,
                                        })}
                                    </a>
                                </Button>
                                {canExportSensitiveTenants && (
                                    <Button variant="destructive" asChild>
                                        <a
                                            href={exportMethod.url(
                                                selectedDataset,
                                                {
                                                    query: {
                                                        ...exportQuery,
                                                        include_sensitive: 1,
                                                    },
                                                },
                                            )}
                                        >
                                            <Download />
                                            {t('Export sensitive fields')}
                                        </a>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: t('Data transfer'), href: index().url }],
};
