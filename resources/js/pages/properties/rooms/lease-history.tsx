import { PluginRegion } from '@/components/shared/plugin-region';
import type { Property, Room } from '@/types';
import { RoomLayout } from './layout';
import LeaseHistoryTab from './tabs/lease-history';

export default function RoomLeaseHistory({ property, room }: { property: Property; room: Room }) {
    return (
        <RoomLayout property={property} room={room} activeTab="lease-history">
            <PluginRegion name="workspace-tab-lease-history">
                <LeaseHistoryTab leases={room.leases ?? []} />
            </PluginRegion>
        </RoomLayout>
    );
}
