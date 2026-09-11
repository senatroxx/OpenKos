import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { TransferImportForm } from '@/components/features/data-transfer/transfer-actions';
import { Heading } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { t } from '@/lib/i18n';

type PageProps = {
    dataset: string;
    datasetLabel: string;
    maxRows: number;
    maxFileSizeMb: number;
    backUrl: string;
    previewUrl: string;
    commitUrl: string;
};

export default function Import({
    dataset,
    datasetLabel,
    maxRows,
    maxFileSizeMb,
    backUrl,
    previewUrl,
    commitUrl,
}: PageProps) {
    return (
        <>
            <Head title={t('Import :dataset', { dataset: datasetLabel })} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Button variant="ghost" className="w-fit" asChild>
                    <Link href={backUrl} prefetch>
                        <ArrowLeft />
                        {t('Back to :dataset', { dataset: datasetLabel })}
                    </Link>
                </Button>

                <Heading
                    title={t('Import :dataset', { dataset: datasetLabel })}
                    description={t(
                        'Upload a versioned CSV, validate every row, then create all records atomically.',
                    )}
                />

                <Card className="max-w-3xl">
                    <CardContent className="pt-6">
                        <TransferImportForm
                            dataset={dataset}
                            datasetLabel={datasetLabel}
                            maxRows={maxRows}
                            maxFileSizeMb={maxFileSizeMb}
                            previewUrl={previewUrl}
                            commitUrl={commitUrl}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
