import { Link } from '@inertiajs/react';
import type { PropertyStats } from '@/types';

export function PropertyOverviewCard({
    property,
}: {
    property: PropertyStats;
}) {
    return (
        <Link
            href={`/properties/${property.slug}`}
            className="block min-w-0 rounded-xl border border-border bg-card p-5 shadow-2xs transition-all duration-150 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-xs"
        >
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <h3 className="min-w-0 truncate text-base font-semibold text-foreground">
                    {property.name}
                </h3>
                <span className="shrink-0 text-xs font-medium text-muted-foreground">
                    {property.total_units} Units
                </span>
            </div>

            <div className="my-3 space-y-1.5">
                <div className="flex min-w-0 items-center justify-between gap-2 text-xs font-medium">
                    <span className="min-w-0 truncate text-muted-foreground">
                        Occupancy Rate
                    </span>
                    <span className="shrink-0 font-bold text-primary tabular-nums">
                        {property.occupancy_percentage}% Occupied
                    </span>
                </div>
                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-primary transition-all duration-300"
                        style={{ width: `${property.occupancy_percentage}%` }}
                    />
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border/60 pt-3 text-xs font-medium text-muted-foreground">
                <span className="flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                    <span className="size-2 shrink-0 rounded-full bg-surface-blue-foreground" />
                    <strong className="font-bold text-foreground tabular-nums">
                        {property.occupied_units}
                    </strong>
                    Occupied
                </span>
                <span className="flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                    <span className="size-2 shrink-0 rounded-full bg-surface-green-foreground" />
                    <strong className="font-bold text-foreground tabular-nums">
                        {property.available_units}
                    </strong>
                    Vacant
                </span>
                <span className="flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                    <span className="size-2 shrink-0 rounded-full bg-surface-amber-foreground" />
                    <strong className="font-bold text-foreground tabular-nums">
                        {property.maintenance_units}
                    </strong>
                    Maintenance
                </span>
            </div>
        </Link>
    );
}
