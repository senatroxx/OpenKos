import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import type { Property } from '@/types';

export default function PropertyOverview({ property }: { property: Property }) {
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;
    const locationLabel = [
        city?.name,
        property?.region?.name,
        property?.postal_code,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">
                    {t('Status:')}
                </span>
                <StatusBadge
                    domain="property"
                    value={property.is_active ? 'active' : 'archived'}
                />
                {property.type && (
                    <Badge variant="outline">
                        {property.type_label ?? property.type}
                    </Badge>
                )}
            </div>

            {property.address && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Address')}
                    </p>
                    <p className="mt-1 text-sm">{property.address}</p>
                    {locationLabel && (
                        <p className="text-sm text-muted-foreground">
                            {locationLabel}
                        </p>
                    )}
                </div>
            )}

            {!property.address && city && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('City')}
                    </p>
                    <p className="mt-1 text-sm">{city.name}</p>
                </div>
            )}

            {property.phone && (
                <div>
                    <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        {t('Phone')}
                    </p>
                    <p className="mt-1 text-sm">{property.phone}</p>
                </div>
            )}

            <div>
                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    {t('Statistics')}
                </p>
                <div className="mt-2 grid grid-cols-3 gap-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.units_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Total Units')}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.occupied_units_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Occupied')}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-2xl font-semibold tabular-nums">
                            {property.tenants_count ?? 0}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('Tenants')}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
