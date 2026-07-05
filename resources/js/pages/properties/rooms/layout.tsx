import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';

type WorkspaceProperty = { id: number; slug: string; name: string };
type WorkspaceRoom = {
    id: number;
    slug: string;
    name: string;
    floor?: string | number | null;
};

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
    const base = `/properties/${property.slug}/rooms/${room.slug}`;

    return (
        <EntityWorkspaceLayout
            title={room.name}
            subtitle={`${property.name} — Floor ${room.floor ?? '—'}`}
            backRoute={`/properties/${property.slug}/rooms`}
            backLabel={`${property.name} rooms`}
        >
            <Head title={`${room.name} — Room`} />

            <WorkspaceTabs
                workspace="room"
                activeTab={activeTab}
                hrefParams={{ id: room.slug, propertyId: property.slug }}
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
