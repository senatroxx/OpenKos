import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { PluginRegion } from '@/components/shared/plugin-region';
import { usePlatformTabs } from '@/lib/platform';
import type { Property } from '@/types';

const TABS = [
    { key: 'overview', label: 'Overview', href: (id: number) => `/properties/${id}` },
    { key: 'rooms', label: 'Rooms', href: (id: number) => `/properties/${id}/rooms` },
    { key: 'leases', label: 'Leases', href: (id: number) => `/properties/${id}/leases` },
    { key: 'documents', label: 'Documents', href: (id: number) => `/properties/${id}/documents` },
] as const;

export function PropertyLayout({
    property,
    activeTab,
    children,
}: {
    property: Property;
    activeTab: string;
    children: ReactNode;
}) {
    // Property tabs are URL-routed, so platform tabs need meta.href
    // (with an optional {id} placeholder); tabs without one are skipped.
    const platformTabs = usePlatformTabs('property')
        .filter((t) => typeof t.meta.href === 'string')
        .map((t) => ({
            key: t.key,
            label: t.label,
            href: () =>
                (t.meta.href as string).replace('{id}', String(property.id)),
        }));

    return (
        <EntityWorkspaceLayout
            title={property.name}
            subtitle={property.address ?? undefined}
            backRoute="/properties"
            backLabel="All properties"
        >
            <Head title={`${property.name} — Property`} />

            <PluginRegion name="workspace-tabs-before" />

            <div className="mb-6 border-b">
                <nav className="-mb-px flex gap-6">
                    {[
                        ...TABS.map((t) => ({
                            key: t.key,
                            label: t.label,
                            href: () => t.href(property.id),
                        })),
                        ...platformTabs,
                    ].map((t) => (
                        <Link
                            key={t.key}
                            href={t.href()}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                activeTab === t.key
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </Link>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />

            {children}
        </EntityWorkspaceLayout>
    );
}
