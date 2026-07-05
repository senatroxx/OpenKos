import { UnitOverview } from '@/components/features';
import type { Property, Unit } from '@/types';
import { UnitLayout } from './layout';

export default function UnitWorkspace({
    property,
    unit,
}: {
    property: Property;
    unit: Unit;
}) {
    return (
        <UnitLayout property={property} unit={unit} activeTab="overview">
            <UnitOverview unit={unit} />
        </UnitLayout>
    );
}
