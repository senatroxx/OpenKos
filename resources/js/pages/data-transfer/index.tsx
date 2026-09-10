import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/DataTransferController';
import {
    TransferExportDialog,
    TransferImportDialog,
} from '@/components/features/data-transfer/transfer-actions';
import { Heading } from '@/components/shared';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { Auth } from '@/types';

type Dataset = {
    value: string;
    label: string;
    importable: boolean;
    exportable: boolean;
};

type PageProps = {
    datasets: Dataset[];
    maxRows: number;
    maxFileSizeMb: number;
};

export default function Index({ datasets, maxRows, maxFileSizeMb }: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [selectedDataset, setSelectedDataset] = useState(
        datasets[0]?.value ?? '',
    );
    const [importOpen, setImportOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);

    const selected = useMemo(
        () => datasets.find((dataset) => dataset.value === selectedDataset),
        [datasets, selectedDataset],
    );
    const selectedDatasetLabel = selected?.label ?? t('Dataset');

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

                <Alert>
                    <AlertTitle>{t('Use an entity page')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'Import and export are available from the Properties, Units, Tenants, and Unit Rates pages. This compatibility page remains available for authorized users.',
                        )}
                    </AlertDescription>
                </Alert>

                {selected && (
                    <div className="grid max-w-xl gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="dataset">{t('Dataset')}</Label>
                            <select
                                id="dataset"
                                value={selectedDataset}
                                onChange={(event) => {
                                    setSelectedDataset(event.target.value);
                                    setImportOpen(false);
                                    setExportOpen(false);
                                }}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {datasets.map((dataset) => (
                                    <option
                                        key={dataset.value}
                                        value={dataset.value}
                                    >
                                        {dataset.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {selected.importable && (
                                <Button onClick={() => setImportOpen(true)}>
                                    {t('Import :dataset', {
                                        dataset: selectedDatasetLabel,
                                    })}
                                </Button>
                            )}
                            {selected.exportable && (
                                <Button
                                    variant="outline"
                                    onClick={() => setExportOpen(true)}
                                >
                                    {t('Export :dataset', {
                                        dataset: selectedDatasetLabel,
                                    })}
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {selected?.importable && (
                <TransferImportDialog
                    key={selectedDataset}
                    dataset={selectedDataset}
                    datasetLabel={selectedDatasetLabel}
                    open={importOpen}
                    onOpenChange={setImportOpen}
                    maxRows={maxRows}
                    maxFileSizeMb={maxFileSizeMb}
                />
            )}
            {selected?.exportable && (
                <TransferExportDialog
                    key={`export-${selectedDataset}`}
                    dataset={selectedDataset}
                    datasetLabel={selectedDatasetLabel}
                    open={exportOpen}
                    onOpenChange={setExportOpen}
                    canExportSensitive={
                        selectedDataset === 'tenants' &&
                        (auth.role === 'owner' ||
                            auth.permissions.includes(
                                'tenants.export_sensitive',
                            ))
                    }
                />
            )}
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: t('Data transfer'), href: index().url }],
};
