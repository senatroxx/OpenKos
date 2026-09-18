import { Head, Link } from '@inertiajs/react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { Badge } from '@/components/ui/badge';
import properties from '@/routes/properties';
import type { UnitTypeLayoutProps } from '@/types';

export function UnitTypeLayout({
    property,
    unitType,
    activeTab,
    actions,
    children,
}: UnitTypeLayoutProps) {
    const base = properties.unitTypes.show.url({ property, unitType });

    return (
        <EntityWorkspaceLayout
            title={unitType.name}
            subtitle={`Property: ${property.name}`}
            backRoute={properties.unitTypes.index.url(property)}
            backLabel={`${property.name} Unit Types`}
            breadcrumbs={
                <nav aria-label="Breadcrumb" className="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                    <Link href={properties.show.url(property)}>Property</Link>
                    <span aria-hidden="true">/</span>
                    <Link href={properties.unitTypes.index.url(property)}>Unit Types</Link>
                    <span aria-hidden="true">/</span>
                    <span className="text-foreground">{unitType.name}</span>
                </nav>
            }
            badges={
                <div className="flex flex-wrap gap-2">
                    <Badge variant={unitType.is_active ? 'secondary' : 'outline'}>
                        {unitType.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                    <Badge variant={unitType.is_published ? 'secondary' : 'outline'}>
                        {unitType.is_published ? 'Listed' : 'Not listed'}
                    </Badge>
                </div>
            }
            actions={actions}
        >
            <Head title={`${unitType.name} — Unit Type`} />
            <WorkspaceTabs
                workspace="unit-type"
                activeTab={activeTab}
                tabs={[
                    { key: 'overview', label: 'Overview', href: base },
                    {
                        key: 'units',
                        label: 'Units',
                        href: properties.unitTypes.units.url({ property, unitType }),
                    },
                    {
                        key: 'pricing',
                        label: 'Pricing',
                        href: properties.unitTypes.rates.index.url({ property, unitType }),
                    },
                    {
                        key: 'listing',
                        label: 'Listing',
                        href: properties.unitTypes.listing.url({ property, unitType }),
                    },
                ]}
            />
            {children}
        </EntityWorkspaceLayout>
    );
}
