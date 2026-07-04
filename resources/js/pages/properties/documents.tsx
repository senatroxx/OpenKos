import type { Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Documents({ property }: { property: Property }) {
    return (
        <PropertyLayout property={property} activeTab="documents">
            <p className="text-sm text-muted-foreground">Documents coming soon.</p>
        </PropertyLayout>
    );
}
