import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';

type WorkspaceProperty = { id: number; name: string };
type WorkspaceRoom = { id: number; name: string; floor?: string | number | null };

export function RoomLayout({
    property,
    room,
    activeTab,
    children,
}: {
    property: WorkspaceProperty;
    room: WorkspaceRoom;
    activeTab: string;
    children: ReactNode;
}) {
    const base = `/properties/${property.id}/rooms/${room.id}`;

    return (
        <EntityWorkspaceLayout
            title={room.name}
            subtitle={`${property.name} — Floor ${room.floor ?? '—'}`}
            backRoute={`/properties/${property.id}/rooms`}
            backLabel={`${property.name} rooms`}
        >
            <Head title={`${room.name} — Room`} />

            <WorkspaceTabs
                workspace="room"
                activeTab={activeTab}
                hrefParams={{ id: room.id, propertyId: property.id }}
                tabs={[
                    { key: 'overview', label: 'Overview', href: base },
                    { key: 'maintenance', label: 'Maintenance', href: `${base}/maintenance-history` },
                    { key: 'lease-history', label: 'Lease History', href: `${base}/lease-history` },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
