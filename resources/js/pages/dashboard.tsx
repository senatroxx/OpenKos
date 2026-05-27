import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

type PropertyStats = {
    id: number;
    name: string;
    total_rooms: number;
    occupied_rooms: number;
    available_rooms: number;
    maintenance_rooms: number;
    unavailable_rooms: number;
    occupancy_percentage: number;
};

type Stats = {
    total_rooms: number;
    occupied_rooms: number;
    available_rooms: number;
    maintenance_rooms: number;
    unavailable_rooms: number;
    occupancy_percentage: number;
    properties: PropertyStats[];
};

export default function Dashboard({ stats }: { stats: Stats }) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Stat cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        label="Occupied Rooms"
                        value={stats.occupied_rooms}
                        bgColor="bg-blue-50 dark:bg-blue-950/20"
                    />
                    <StatCard
                        label="Available Rooms"
                        value={stats.available_rooms}
                        bgColor="bg-green-50 dark:bg-green-950/20"
                    />
                    <StatCard
                        label="Maintenance"
                        value={stats.maintenance_rooms}
                        bgColor="bg-amber-50 dark:bg-amber-950/20"
                    />
                    <StatCard
                        label="Occupancy Rate"
                        value={`${stats.occupancy_percentage}%`}
                        bgColor="bg-indigo-50 dark:bg-indigo-950/20"
                        isPercentage
                    />
                </div>

                {/* Property breakdown */}
                <div>
                    <h2 className="mb-4 text-sm font-medium text-muted-foreground uppercase tracking-wider">
                        Per Property
                    </h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {stats.properties.map((property) => (
                            <PropertyCard key={property.id} property={property} />
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    bgColor,
    isPercentage,
}: {
    label: string;
    value: number | string;
    bgColor: string;
    isPercentage?: boolean;
}) {
    return (
        <div
            className={`rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border ${bgColor}`}
        >
            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                {label}
            </p>
            <p className={`mt-2 text-3xl font-bold tabular-nums ${isPercentage ? 'text-indigo-600 dark:text-indigo-400' : ''}`}>
                {value}
            </p>
            {isPercentage && (
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-indigo-200 dark:bg-indigo-900/40">
                    <div
                        className="h-full rounded-full bg-indigo-600 dark:bg-indigo-400 transition-all"
                        style={{ width: `${value}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function PropertyCard({ property }: { property: PropertyStats }) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <h3 className="truncate text-sm font-semibold">{property.name}</h3>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <p className="text-xs text-muted-foreground">Occupied</p>
                    <p className="mt-0.5 text-lg font-bold tabular-nums text-blue-600 dark:text-blue-400">
                        {property.occupied_rooms}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Available</p>
                    <p className="mt-0.5 text-lg font-bold tabular-nums text-green-600 dark:text-green-400">
                        {property.available_rooms}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Maintenance</p>
                    <p className="mt-0.5 text-lg font-bold tabular-nums text-amber-600 dark:text-amber-400">
                        {property.maintenance_rooms}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Occupancy</p>
                    <p className="mt-0.5 text-lg font-bold tabular-nums text-indigo-600 dark:text-indigo-400">
                        {property.occupancy_percentage}%
                    </p>
                </div>
            </div>

            <div className="mt-4">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {property.occupied_rooms} / {property.total_rooms} rooms
                    </span>
                    <span>{property.occupancy_percentage}%</span>
                </div>
                <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-indigo-500 transition-all"
                        style={{ width: `${property.occupancy_percentage}%` }}
                    />
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
