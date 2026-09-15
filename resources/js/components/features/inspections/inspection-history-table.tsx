import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import inspectionRoutes from '@/routes/inspections';
import type {
    Inspection,
    InspectionTemplateOption,
    PaginatedData,
    TableMeta,
} from '@/types';
import { InspectionFormSheet } from './inspection-form-sheet';

const TYPE_LABELS: Record<string, string> = {
    move_in: 'Move-in',
    move_out: 'Move-out',
    periodic: 'Periodic',
};

const contextualColumns: TableColumn<Inspection>[] = [
    {
        key: 'template_name',
        label: 'Checklist',
        sortable: true,
        className: 'font-medium',
    },
    {
        key: 'inspection_type',
        label: 'Type',
        sortable: true,
        render: (inspection) =>
            TYPE_LABELS[inspection.inspection_type] ??
            inspection.inspection_type,
    },
    {
        key: '_scope',
        label: 'Scope',
        render: (inspection) =>
            inspection.lease?.reference ?? inspection.unit?.name ?? 'Property',
    },
    {
        key: 'inspection_date',
        label: 'Date',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (inspection) => formatDate(inspection.inspection_date),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (inspection) => (
            <StatusBadge domain="inspection" value={inspection.status} />
        ),
    },
];

const globalColumns: TableColumn<Inspection>[] = [
    {
        key: 'property_name',
        label: 'Property',
        className: 'font-medium',
        render: (inspection) => inspection.property?.name ?? '—',
    },
    {
        key: 'unit_name',
        label: 'Unit',
        render: (inspection) => inspection.unit?.name ?? 'Property only',
    },
    {
        key: 'lease_context',
        label: 'Lease / Tenant',
        render: (inspection) =>
            inspection.lease
                ? [
                      inspection.lease.reference,
                      inspection.lease.primary_tenant?.name,
                  ]
                      .filter(Boolean)
                      .join(' · ') || '—'
                : '—',
    },
    {
        key: 'inspection_type',
        label: 'Type',
        sortable: true,
        render: (inspection) =>
            TYPE_LABELS[inspection.inspection_type] ??
            inspection.inspection_type,
    },
    {
        key: 'inspection_date',
        label: 'Date',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (inspection) => formatDate(inspection.inspection_date),
    },
    {
        key: 'template_name',
        label: 'Template',
        sortable: true,
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (inspection) => (
            <StatusBadge domain="inspection" value={inspection.status} />
        ),
    },
    {
        key: 'inspector_name',
        label: 'Inspector',
        render: (inspection) => inspection.inspector?.name ?? '—',
    },
    {
        key: '_actions',
        label: 'Actions',
        className: 'text-right',
        render: (inspection) => (
            <Button
                variant="ghost"
                size="sm"
                onClick={(event) => {
                    event.stopPropagation();
                    router.visit(inspectionRoutes.show(inspection.id));
                }}
            >
                {t('View')}
            </Button>
        ),
    },
];

export function InspectionHistoryTable({
    context,
    historyUrl,
    createUrl,
    inspections,
    templates,
    canCreate,
    sort = '-inspection_date',
    search = '',
    inspection_type = '',
    status = '',
    property_id = '',
    per_page = 15,
    table,
}: {
    context: 'property' | 'unit' | 'lease' | 'global';
    historyUrl: string;
    createUrl?: string;
    inspections: PaginatedData<Inspection>;
    templates: InspectionTemplateOption[];
    canCreate: boolean;
    sort?: string;
    search?: string;
    inspection_type?: string;
    status?: string;
    property_id?: string;
    per_page?: number;
    table: TableMeta;
}) {
    const [formOpen, setFormOpen] = useState(false);
    const isGlobal = context === 'global';

    return (
        <>
            <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-lg font-medium">
                        {t(isGlobal ? 'All Inspections' : 'Inspection history')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            isGlobal
                                ? 'Review inspections across accessible properties.'
                                : 'Review draft and completed condition records.',
                        )}
                    </p>
                </div>
                {!isGlobal && canCreate && (
                    <Button onClick={() => setFormOpen(true)}>
                        <Plus className="size-4" />
                        {t('New inspection')}
                    </Button>
                )}
            </div>

            <WorkspaceTable
                url={historyUrl}
                noun="inspections"
                rows={inspections}
                columns={isGlobal ? globalColumns : contextualColumns}
                tableMeta={table}
                sort={sort}
                search={search}
                perPage={per_page}
                filterValues={{
                    inspection_type,
                    status,
                    ...(isGlobal ? { property_id } : {}),
                }}
                defaultSort="-inspection_date"
                searchPlaceholder={t(
                    isGlobal
                        ? 'Search properties, units, leases...'
                        : 'Search checklists...',
                )}
                emptyMessage={t('No inspections recorded yet.')}
                onRowClick={(inspection) =>
                    router.visit(inspectionRoutes.show(inspection.id))
                }
            />

            {!isGlobal && createUrl && (
                <InspectionFormSheet
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    createUrl={createUrl}
                    context={context}
                    templates={templates}
                />
            )}
        </>
    );
}
