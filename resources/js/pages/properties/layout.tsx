import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import type { Property } from '@/types';

export function PropertyLayout({
    property,
    activeTab,
    children,
}: {
    property: Property;
    activeTab: string;
    children: ReactNode;
}) {
    return (
        <EntityWorkspaceLayout
            title={property.name}
            subtitle={property.address ?? undefined}
            backRoute="/properties"
            backLabel="All properties"
        >
            <Head title={`${property.name} — Property`} />

            <WorkspaceTabs
                workspace="property"
                activeTab={activeTab}
                hrefParams={{ id: property.id }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/properties/${property.id}`,
                    },
                    {
                        key: 'rooms',
                        label: 'Rooms',
                        href: `/properties/${property.id}/rooms`,
                    },
                    {
                        key: 'leases',
                        label: 'Leases',
                        href: `/properties/${property.id}/leases`,
                    },
                    {
                        key: 'documents',
                        label: 'Documents',
                        href: `/properties/${property.id}/documents`,
                    },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
