import { UnitOverview } from '@/components/features';
import type { Property, Unit } from '@/types';
import { RoomLayout } from './layout';

export default function RoomWorkspace({
    property,
    unit,
}: {
    property: Property;
    unit: Unit;
}) {
    return (
        <RoomLayout property={property} unit={unit} activeTab="overview">
            <UnitOverview unit={unit} />
        </RoomLayout>
    );
}
