import { RoomOverview } from '@/components/features';
import type { Property, Room } from '@/types';
import { RoomLayout } from './layout';

export default function RoomWorkspace({ property, room }: { property: Property; room: Room }) {
    return (
        <RoomLayout property={property} room={room} activeTab="overview">
            <RoomOverview room={room} />
        </RoomLayout>
    );
}
