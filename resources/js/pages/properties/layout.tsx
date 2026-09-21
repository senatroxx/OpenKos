import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import {
    supportsPropertyPricing,
    supportsUnitInventory,
} from '@/lib/property-rental-mode';
import properties from '@/routes/properties';
import type { Property } from '@/types';

export function PropertyLayout({
    property,
    activeTab,
    actions,
    children,
}: {
    property: Property;
    activeTab: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const hasUnitInventory = supportsUnitInventory(property.rental_mode);
    const hasPropertyPricing = supportsPropertyPricing(property.rental_mode);

    const tabs = [
        {
            key: 'overview',
            label: 'Overview',
            href: properties.show.url(property),
        },
        ...(hasUnitInventory
            ? [
                  {
                      key: 'units',
                      label: 'Units',
                      href: properties.units.index.url(property),
                  },
                  {
                      key: 'unit-types',
                      label: 'Unit Types',
                      href: properties.unitTypes.index.url(property),
                  },
              ]
            : []),
        ...(hasPropertyPricing
            ? [
                  {
                      key: 'pricing',
                      label: 'Pricing',
                      href: properties.pricing.index.url(property),
                  },
              ]
            : []),
        {
            key: 'leases',
            label: 'Leases',
            href: properties.workspace.leases.url(property),
        },
        {
            key: 'inspections',
            label: 'Inspections',
            href: properties.workspace.inspections.url(property),
        },
        {
            key: 'listing',
            label: 'Listing',
            href: properties.listing.url(property),
        },
        {
            key: 'documents',
            label: 'Documents',
            href: properties.workspace.documents.url(property),
        },
    ];

    return (
        <EntityWorkspaceLayout
            title={property.name}
            subtitle={property.address ?? undefined}
            backRoute={properties.index.url()}
            backLabel="All properties"
            actions={actions}
        >
            <Head title={`${property.name} — Property`} />

            <WorkspaceTabs
                workspace="property"
                activeTab={activeTab}
                hrefParams={{ id: property.slug }}
                tabs={tabs}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
