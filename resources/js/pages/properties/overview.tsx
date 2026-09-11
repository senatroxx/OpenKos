import { PropertyOverview } from '@/components/features';
import type { Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Overview({ property }: { property: Property }) {
    return (
        <PropertyLayout property={property} activeTab="overview">
            <PropertyOverview property={property} />
        </PropertyLayout>
    );
}
