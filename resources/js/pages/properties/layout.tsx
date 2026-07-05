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
                hrefParams={{ id: property.slug }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/properties/${property.slug}`,
                    },
                    {
                        key: 'units',
                        label: 'Rooms',
                        href: `/properties/${property.slug}/units`,
                    },
                    {
                        key: 'leases',
                        label: 'Leases',
                        href: `/properties/${property.slug}/leases`,
                    },
                    {
                        key: 'documents',
                        label: 'Documents',
                        href: `/properties/${property.slug}/documents`,
                    },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
