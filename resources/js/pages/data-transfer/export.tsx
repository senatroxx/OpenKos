import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { TransferExportForm } from '@/components/features/data-transfer/transfer-actions';
import { Heading } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTable } from '@/hooks/use-table';
import { t } from '@/lib/i18n';
import type { TableFilterMeta } from '@/types';
import type { QueryParams } from '@/wayfinder';

type PageProps = {
    dataset: string;
    datasetLabel: string;
    pageUrl: string;
    backUrl: string;
    context: Record<string, string>;
    filters: TableFilterMeta[];
    initialQuery: Record<string, string>;
    includeArchivedDefault: boolean;
    canExportSensitive: boolean;
    searchSupported: boolean;
};

export default function Export({
    dataset,
    datasetLabel,
    pageUrl,
    backUrl,
    context,
    filters,
    initialQuery,
    includeArchivedDefault,
    canExportSensitive,
    searchSupported,
}: PageProps) {
    const params: Record<string, string> = Object.fromEntries(
        filters.map((filter) => [filter.key, initialQuery[filter.key] ?? '']),
    );

    if (searchSupported) {
        params.search = initialQuery.search ?? '';
    }

    const table = useTable({
        routeFn: () => ({ url: pageUrl }),
        params,
    });

    const exportQuery = {
        ...context,
        ...(searchSupported && table.searchValue
            ? { search: table.searchValue }
            : {}),
        ...table.activeFilters,
    } satisfies QueryParams;

    return (
        <>
            <Head title={t('Export :dataset', { dataset: datasetLabel })} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Button variant="ghost" className="w-fit" asChild>
                    <Link href={backUrl} prefetch>
                        <ArrowLeft />
                        {t('Back to :dataset', { dataset: datasetLabel })}
                    </Link>
                </Button>

                <Heading
                    title={t('Export :dataset', { dataset: datasetLabel })}
                    description={t(
                        'Export the current filters in the stable versioned CSV format. Active records are included by default.',
                    )}
                />

                <Card>
                    <CardContent className="grid gap-6 pt-6">
                        {(filters.length > 0 || searchSupported) && (
                            <FilterBar
                                filters={filters}
                                activeFilters={table.activeFilters}
                                activeFilterCount={table.activeFilterCount}
                                onToggleOption={table.toggleFilterOption}
                                onClearAll={table.clearAllFilters}
                                alwaysOpen
                                searchInput={
                                    searchSupported ? (
                                        <SearchInput
                                            value={table.searchValue}
                                            onChange={table.onSearchChange}
                                            onClear={table.clearSearch}
                                            placeholder={t('Search :dataset', {
                                                dataset: datasetLabel,
                                            })}
                                        />
                                    ) : (
                                        <div className="h-9" />
                                    )
                                }
                            />
                        )}

                        <div className="border-t pt-6">
                            <TransferExportForm
                                key={
                                    includeArchivedDefault
                                        ? 'archived'
                                        : 'active'
                                }
                                dataset={dataset}
                                datasetLabel={datasetLabel}
                                query={exportQuery}
                                includeArchivedDefault={includeArchivedDefault}
                                canExportSensitive={canExportSensitive}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
