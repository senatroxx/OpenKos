import { PropertyOverview } from '@/components/features';
import type { Amenity, Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Overview({
    property,
    amenities,
}: {
    property: Property;
    amenities: Amenity[];
}) {
    return (
        <PropertyLayout property={property} activeTab="overview">
            <PropertyOverview property={property} amenities={amenities} />
        </PropertyLayout>
    );
}
