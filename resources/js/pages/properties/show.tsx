import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PropertyOverview } from '@/components/features';
import { PluginRegion } from '@/components/shared/plugin-region';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import type { Property } from '@/types';

const TABS = ['Overview', 'Rooms', 'Leases', 'Documents'] as const;

export default function PropertyWorkspace({ property }: { property: Property }) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Overview');

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
                    {TABS.map((t) => (
                        <button
                            key={t}
                            type="button"
                            onClick={() => setTab(t)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                tab === t
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t}
                        </button>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />

            {tab === 'Overview' && <PropertyOverview property={property} />}
            {tab === 'Rooms' && (
                <PluginRegion name="workspace-tab-rooms">
                    <p className="text-sm text-muted-foreground">Rooms list coming soon.</p>
                </PluginRegion>
            )}
            {tab === 'Leases' && (
                <PluginRegion name="workspace-tab-leases">
                    <p className="text-sm text-muted-foreground">Leases list coming soon.</p>
                </PluginRegion>
            )}
            {tab === 'Documents' && (
                <PluginRegion name="workspace-tab-documents">
                    <p className="text-sm text-muted-foreground">Documents coming soon.</p>
                </PluginRegion>
            )}
        </EntityWorkspaceLayout>
    );
}
