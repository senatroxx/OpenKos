import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { RoomOverview } from '@/components/features';
import { PluginRegion } from '@/components/shared/plugin-region';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import type { Property, Room } from '@/types';

const TABS = ['Overview', 'Maintenance', 'Lease History'] as const;

export default function RoomWorkspace({ property, room }: { property: Property; room: Room }) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Overview');

    return (
        <EntityWorkspaceLayout
            title={room.name}
            subtitle={`${property.name} — Floor ${room.floor ?? '—'}`}
            backRoute={`/properties/${property.id}/rooms`}
            backLabel={`${property.name} rooms`}
        >
            <Head title={`${room.name} — Room`} />

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

            {tab === 'Overview' && <RoomOverview room={room} />}
            {tab === 'Maintenance' && (
                <PluginRegion name="workspace-tab-maintenance">
                    <p className="text-sm text-muted-foreground">Maintenance tickets coming soon.</p>
                </PluginRegion>
            )}
            {tab === 'Lease History' && (
                <PluginRegion name="workspace-tab-lease-history">
                    <p className="text-sm text-muted-foreground">Lease history coming soon.</p>
                </PluginRegion>
            )}
        </EntityWorkspaceLayout>
    );
}
