import { Link } from '@inertiajs/react';
import type { Property } from '@/types';
import { PropertyLayout } from './layout';

export default function Leases({ property }: { property: Property }) {
    return (
        <PropertyLayout property={property} activeTab="leases">
            <div className="rounded-lg border p-6 text-center">
                <p className="mb-2 text-sm text-muted-foreground">
                    {property.tenants_count} active {property.tenants_count === 1 ? 'lease' : 'leases'} across {property.rooms_count} {property.rooms_count === 1 ? 'room' : 'rooms'}
                </p>
                <Link href="/leases" className="text-sm text-blue-600 hover:underline">
                    View all leases &rarr;
                </Link>
            </div>
        </PropertyLayout>
    );
}
