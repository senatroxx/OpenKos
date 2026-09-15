import { InspectionHistoryTable } from '@/components/features/inspections';
import leases from '@/routes/leases';
import type {
    Inspection,
    InspectionTemplateOption,
    PaginatedData,
    TableMeta,
    WorkspaceLease,
} from '@/types';
import { LeaseLayout } from './layout';

export default function LeaseInspections({
    lease,
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
    lease: WorkspaceLease;
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
        <LeaseLayout lease={lease} activeTab="inspections">
            <InspectionHistoryTable
                context="lease"
                historyUrl={leases.workspace.inspections.url(lease)}
                createUrl={leases.inspections.store.url(lease)}
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
        </LeaseLayout>
    );
}
