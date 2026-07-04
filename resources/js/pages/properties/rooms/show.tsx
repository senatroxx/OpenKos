import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { RoomOverview } from '@/components/features';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { PluginRegion } from '@/components/shared/plugin-region';
import { usePlatformTabs } from '@/lib/platform';
import type { MaintenanceTicket, Property, Room } from '@/types';
import LeaseHistoryTab from './tabs/lease-history';
import MaintenanceTab from './tabs/maintenance';

const TABS = ['Overview', 'Maintenance', 'Lease History'] as const;

export default function RoomWorkspace({ property, room }: { property: Property; room: Room }) {
    const platformTabs = usePlatformTabs('room');
    const [tab, setTab] = useState<string>('Overview');
    const tickets = (room as unknown as Record<string, unknown>).maintenanceTickets as MaintenanceTicket[] | undefined;
    const leases = room.leases ?? [];

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
                    {[
                        ...TABS.map((t) => ({ key: t, label: t })),
                        ...platformTabs,
                    ].map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                tab === t.key
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />

            {tab === 'Overview' && <RoomOverview room={room} />}
            {tab === 'Maintenance' && <PluginRegion name="workspace-tab-maintenance"><MaintenanceTab tickets={tickets ?? []} /></PluginRegion>}
            {tab === 'Lease History' && <PluginRegion name="workspace-tab-lease-history"><LeaseHistoryTab leases={leases} /></PluginRegion>}
            {platformTabs.map(
                (t) =>
                    tab === t.key && (
                        <PluginRegion key={t.key} name={`workspace-tab-${t.key}`} />
                    ),
            )}
        </EntityWorkspaceLayout>
    );
}
