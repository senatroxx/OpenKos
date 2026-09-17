import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import { WholePropertyLeaseSheet } from '@/components/features';
import { PluginRegion } from '@/components/shared/plugin-region';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTable } from '@/components/shared/workspace-table';
import { Button } from '@/components/ui/button';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import { supportsPropertyPricing } from '@/lib/property-rental-mode';
import leaseRoutes from '@/routes/leases';
import properties from '@/routes/properties';
import type { Lease, PropertyLeasesPageProps } from '@/types';
import { PropertyLayout } from './layout';

const columns: TableColumn<Lease>[] = [
    {
        key: 'reference',
        label: 'Reference',
        sortable: true,
        className: 'font-mono text-xs',
        render: (l) => l.reference ?? `#${l.id}`,
    },
    {
        key: '_target',
        label: 'Target',
        className: 'font-medium',
        render: (l) =>
            l.target_type === 'whole_property'
                ? 'Entire property'
                : (l.unit?.name ?? '—'),
    },
    {
        key: '_tenants',
        label: 'Tenants',
        render: (l) => {
            const occupants = l.tenants.length
                ? l.tenants
                : l.primary_tenant
                  ? [l.primary_tenant]
                  : [];

            return occupants.length ? (
                <div className="text-sm">
                    {occupants.map((t) => (
                        <div key={t.id}>{t.name}</div>
                    ))}
                </div>
            ) : (
                '—'
            );
        },
    },
    {
        key: 'start_date',
        label: 'Start',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => formatDate(l.start_date),
    },
    {
        key: 'end_date',
        label: 'End',
        sortable: true,
        className: 'text-muted-foreground tabular-nums',
        render: (l) => (l.end_date ? formatDate(l.end_date) : 'ongoing'),
    },
    {
        key: 'rent_amount',
        label: 'Rent',
        sortable: true,
        className: 'tabular-nums',
        render: (l) => formatPrice(l.rent_amount, l.currency),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (l) => <StatusBadge domain="lease" value={l.status} />,
    },
];

export default function PropertyLeases({
    property,
    leases,
    sort = '-start_date',
    search = '',
    status = '',
    per_page = 15,
    table,
    tenants,
}: PropertyLeasesPageProps) {
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <PropertyLayout property={property} activeTab="leases">
            <PluginRegion name="workspace-tab-leases">
                <div className="space-y-4">
                    {supportsPropertyPricing(property.rental_mode) && (
                        <div className="flex justify-end">
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" />
                                {t('New whole-property lease')}
                            </Button>
                        </div>
                    )}
                    <WorkspaceTable
                        url={properties.workspace.leases.url(property)}
                        noun="leases"
                        rows={leases}
                        columns={columns}
                        tableMeta={table}
                        sort={sort}
                        search={search}
                        perPage={per_page}
                        filterValues={{ status }}
                        defaultSort="-start_date"
                        searchPlaceholder="Search by reference, tenant, or unit..."
                        emptyMessage="No leases for this property yet."
                        onRowClick={(l) => router.get(leaseRoutes.show.url(l))}
                    />
                </div>
            </PluginRegion>
            {supportsPropertyPricing(property.rental_mode) && (
                <WholePropertyLeaseSheet
                    property={property}
                    tenants={tenants}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}
        </PropertyLayout>
    );
}
