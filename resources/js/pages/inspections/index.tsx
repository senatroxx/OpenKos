import { Head } from '@inertiajs/react';
import { InspectionHistoryTable } from '@/components/features/inspections/inspection-history-table';
import { t } from '@/lib/i18n';
import inspectionRoutes from '@/routes/inspections';
import type {
    Inspection,
    InspectionTemplateOption,
    PaginatedData,
    TableMeta,
} from '@/types';

export default function AllInspections({
    inspections,
    templates,
    sort = '-inspection_date',
    search = '',
    property_id = '',
    inspection_type = '',
    status = '',
    per_page = 15,
    table,
}: {
    inspections: PaginatedData<Inspection>;
    templates: InspectionTemplateOption[];
    sort?: string;
    search?: string;
    property_id?: string;
    inspection_type?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <>
            <Head title={t('All Inspections')} />
            <div className="px-4 py-6">
                <InspectionHistoryTable
                    context="global"
                    historyUrl={inspectionRoutes.index.url()}
                    inspections={inspections}
                    templates={templates}
                    canCreate={false}
                    sort={sort}
                    search={search}
                    property_id={property_id}
                    inspection_type={inspection_type}
                    status={status}
                    per_page={per_page}
                    table={table}
                />
            </div>
        </>
    );
}
