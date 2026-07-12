import { FileDown } from 'lucide-react';
import type { TableColumn } from '@/components/data-table';
import { PluginRegion } from '@/components/shared/plugin-region';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { formatDate } from '@/lib/formatters';
import type {
    PaginatedData,
    TableMeta,
    TenantDocument,
    WorkspaceTenant,
} from '@/types';
import { TenantLayout } from './layout';

const columns: TableColumn<TenantDocument>[] = [
    {
        key: 'original_name',
        label: 'Name',
        sortable: true,
        className: 'font-medium',
    },
    {
        key: 'type',
        label: 'Type',
        sortable: true,
        className: 'capitalize text-muted-foreground',
    },
    {
        key: 'created_at',
        label: 'Uploaded',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (d) => formatDate(d.created_at),
    },
    {
        key: '_download',
        label: '',
        render: (d) => (
            <a
                href={d.download_url}
                download={d.original_name}
                onClick={(e) => e.stopPropagation()}
                className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
            >
                <FileDown className="size-4" />
            </a>
        ),
    },
];

export default function TenantDocuments({
    tenant,
    documents,
    sort = '-created_at',
    search = '',
    type = '',
    per_page = 15,
    table,
}: {
    tenant: WorkspaceTenant;
    documents: PaginatedData<TenantDocument>;
    sort?: string;
    search?: string;
    type?: string;
    per_page?: number;
    table: TableMeta;
}) {
    return (
        <TenantLayout tenant={tenant} activeTab="documents">
            <PluginRegion name="workspace-tab-documents">
                <WorkspaceTable
                    url={`/tenants/${tenant.id}/documents`}
                    noun="documents"
                    rows={documents}
                    columns={columns}
                    tableMeta={table}
                    sort={sort}
                    search={search}
                    perPage={per_page}
                    filterValues={{ type }}
                    defaultSort="-created_at"
                    searchPlaceholder="Search by file name..."
                    emptyMessage="No documents uploaded yet."
                />
            </PluginRegion>
        </TenantLayout>
    );
}
