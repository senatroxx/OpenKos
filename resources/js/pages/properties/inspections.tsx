import { InspectionHistoryTable } from '@/components/features/inspections';
import properties from '@/routes/properties';
import type {
    InspectionTemplateOption,
    Inspection,
    PaginatedData,
    Property,
    TableMeta,
} from '@/types';
import { PropertyLayout } from './layout';

export default function PropertyInspections({
    property,
    inspections,
    templates,
    can,
    sort = '-inspection_date',
    search = '',
    inspection_type = '',
    status = '',
    per_page = 15,
    table,
}: {
    property: Property;
    inspections: PaginatedData<Inspection>;
    templates: InspectionTemplateOption[];
    can: { create: boolean };
    sort?: string;
    search?: string;
    inspection_type?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <PropertyLayout property={property} activeTab="inspections">
            <InspectionHistoryTable
                context="property"
                historyUrl={properties.workspace.inspections.url(property)}
                createUrl={properties.inspections.store.url(property)}
                inspections={inspections}
                templates={templates}
                canCreate={can.create}
                sort={sort}
                search={search}
                inspection_type={inspection_type}
                status={status}
                per_page={per_page}
                table={table}
            />
        </PropertyLayout>
    );
}
