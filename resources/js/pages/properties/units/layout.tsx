import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';

type WorkspaceProperty = { id: number; slug: string; name: string };
type WorkspaceUnit = {
    id: number;
    slug: string;
    name: string;
    floor?: string | number | null;
};

export function UnitLayout({
    property,
    unit,
    activeTab,
    children,
}: {
    property: WorkspaceProperty;
    unit: WorkspaceUnit;
    activeTab: string;
    children: ReactNode;
}) {
    const base = `/properties/${property.slug}/units/${unit.slug}`;

    return (
        <EntityWorkspaceLayout
            title={unit.name}
            subtitle={`${property.name} — Floor ${unit.floor ?? '—'}`}
            backRoute={`/properties/${property.slug}/units`}
            backLabel={`${property.name} units`}
        >
            <Head title={`${unit.name} — Unit`} />

            <WorkspaceTabs
                workspace="unit"
                activeTab={activeTab}
                hrefParams={{ id: unit.slug, propertyId: property.slug }}
                tabs={[
                    { key: 'overview', label: 'Overview', href: base },
                    {
                        key: 'maintenance',
                        label: 'Maintenance',
                        href: `${base}/maintenance-history`,
                    },
                    {
                        key: 'lease-history',
                        label: 'Lease History',
                        href: `${base}/lease-history`,
                    },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
