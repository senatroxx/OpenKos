import { Badge } from '@/components/ui/badge';
import type { Property } from '@/types';
import { PROPERTY_TYPE_LABELS } from '@/types/models';

export default function PropertyOverview({ property }: { property: Property }) {
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;
    const locationLabel = [city?.name, property?.region?.name, property?.postal_code]
        .filter(Boolean)
        .join(', ');

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">Status:</span>
                {property.is_active ? (
                    <Badge variant="default" className="bg-green-600">Active</Badge>
                ) : (
                    <Badge variant="secondary">Archived</Badge>
                )}
                {property.type && (
                    <Badge variant="outline">
                        {PROPERTY_TYPE_LABELS[property.type] ?? property.type}
                    </Badge>
                )}
            </div>

            {property.address && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Address</p>
                    <p className="mt-1 text-sm">{property.address}</p>
                    {locationLabel && (
                        <p className="text-sm text-muted-foreground">{locationLabel}</p>
                    )}
                </div>
            )}

            {!property.address && city && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">City</p>
                    <p className="mt-1 text-sm">{city.name}</p>
                </div>
            )}

            {property.phone && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Phone</p>
                    <p className="mt-1 text-sm">{property.phone}</p>
                </div>
            )}

            <div>
                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">Statistics</p>
                <div className="mt-2 grid grid-cols-3 gap-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">{property.rooms_count ?? 0}</p>
                        <p className="text-xs text-muted-foreground">Total Rooms</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">{property.occupied_rooms_count ?? 0}</p>
                        <p className="text-xs text-muted-foreground">Occupied</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">{property.tenants_count ?? 0}</p>
                        <p className="text-xs text-muted-foreground">Tenants</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
