import { InspectionHistoryTable } from '@/components/features/inspections';
import properties from '@/routes/properties';
import type {
    Inspection,
    InspectionTemplateOption,
    PaginatedData,
    TableMeta,
    WorkspaceProperty,
    WorkspaceUnit,
} from '@/types';
import { UnitLayout } from './layout';

export default function UnitInspections({
    property,
    unit,
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
    property: WorkspaceProperty;
    unit: WorkspaceUnit;
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
        <UnitLayout property={property} unit={unit} activeTab="inspections">
            <InspectionHistoryTable
                context="unit"
                historyUrl={properties.units.inspections.url({
                    property: property.slug,
                    unit: unit.slug,
                })}
                createUrl={properties.units.inspections.store.url({
                    property: property.slug,
                    unit: unit.slug,
                })}
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
        </UnitLayout>
    );
}
