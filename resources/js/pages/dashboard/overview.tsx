import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PropertyFormSheet, TenantFormSheet, TicketFormSheet } from '@/components/features';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import type { Finance, PropertyStats, RecentActivityEntry, Stats } from '@/types';

interface Attention {
    overdue_invoices: { count: number; amount: number };
    due_today: number;
    open_maintenance: number;
    leases_ending_soon: number;
}

type MaintenanceProperty = { id: number; name: string };
type MaintenanceUnit = {
    id: number;
    slug: string;
    name: string;
    property_id: number;
    status: string;
    active_lease_count: number;
    has_maintenance_transfer?: number;
    leases?: { tenants: { id: number; name: string }[] }[];
};

export default function Overview({
    attention,
    finance,
    stats,
    recent_activity,
    properties,
    units,
}: {
    attention: Attention;
    finance: Finance;
    stats: Stats;
    recent_activity: RecentActivityEntry[];
    properties: MaintenanceProperty[];
    units: MaintenanceUnit[];
}) {
    const [tenantSheetOpen, setTenantSheetOpen] = useState(false);
    const [propertySheetOpen, setPropertySheetOpen] = useState(false);
    const [ticketSheetOpen, setTicketSheetOpen] = useState(false);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

                <section>
                    <h2 className="mb-4 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                        Today&apos;s Attention
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <AttentionCard
                            label="Overdue Invoices"
                            count={attention.overdue_invoices.count}
                            amount={attention.overdue_invoices.amount}
                            bgColor="bg-rose-50 dark:bg-rose-950/20"
                            textColor="text-rose-600 dark:text-rose-400"
                        />
                        <AttentionCard
                            label="Due Today"
                            count={attention.due_today}
                            bgColor="bg-amber-50 dark:bg-amber-950/20"
                            textColor="text-amber-600 dark:text-amber-400"
                        />
                        <AttentionCard
                            label="Open Maintenance"
                            count={attention.open_maintenance}
                            bgColor="bg-orange-50 dark:bg-orange-950/20"
                            textColor="text-orange-600 dark:text-orange-400"
                        />
                        <AttentionCard
                            label="Leases Ending Soon"
                            count={attention.leases_ending_soon}
                            bgColor="bg-sky-50 dark:bg-sky-950/20"
                            textColor="text-sky-600 dark:text-sky-400"
                        />
                    </div>
                </section>

                <section>
                    <h2 className="mb-4 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                        Quick Access
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <QuickAccessButton
                            label="Add Tenant"
                            onClick={() => setTenantSheetOpen(true)}
                        />
                        <QuickAccessButton
                            label="Add Property"
                            onClick={() => setPropertySheetOpen(true)}
                        />
                        <QuickAccessButton
                            label="Report Maintenance"
                            onClick={() => setTicketSheetOpen(true)}
                        />
                        <QuickAccessLink label="Assign Tenant" href="/tenants" />
                        <QuickAccessLink label="Collect Rent" href="/dashboard/rent" />
                    </div>
                </section>

                <section>
                    <h2 className="mb-4 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                        Business Health
                    </h2>
                    <div className="grid gap-4 md:grid-cols-4">
                        <StatCard
                            label="Revenue This Month"
                            value={formatRupiah(finance.revenue_this_month)}
                            bgColor="bg-emerald-50 dark:bg-emerald-950/20"
                        />
                        <StatCard
                            label="Monthly Potential"
                            value={formatRupiah(finance.monthly_potential)}
                            bgColor="bg-violet-50 dark:bg-violet-950/20"
                        />
                        <StatCard
                            label="Outstanding"
                            value={formatRupiah(finance.outstanding)}
                            bgColor="bg-rose-50 dark:bg-rose-950/20"
                        />
                        <StatCard
                            label="Collection Rate"
                            value={finance.collection_rate}
                            bgColor="bg-cyan-50 dark:bg-cyan-950/20"
                            isPercentage
                        />
                    </div>
                </section>

                <section>
                    <h2 className="mb-4 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                        Property Overview
                    </h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {stats.properties.map((property) => (
                            <PropertyCard
                                key={property.id}
                                property={property}
                            />
                        ))}
                    </div>
                </section>

                {recent_activity.length > 0 && (
                    <section>
                        <h2 className="mb-4 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                            Recent Activity
                        </h2>
                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="space-y-3">
                                {recent_activity.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center gap-3 text-sm"
                                    >
                                        <div className="h-2 w-2 shrink-0 rounded-full bg-muted-foreground/40" />
                                        <span className="text-foreground">{entry.description}</span>
                                        <span className="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums">
                                            {relativeTime(entry.created_at)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                )}
            </div>

            <TenantFormSheet
                open={tenantSheetOpen}
                onOpenChange={setTenantSheetOpen}
            />
            <PropertyFormSheet
                open={propertySheetOpen}
                onOpenChange={setPropertySheetOpen}
            />
            <TicketFormSheet
                open={ticketSheetOpen}
                onOpenChange={setTicketSheetOpen}
                properties={properties}
                units={units}
            />
        </>
    );
}

function AttentionCard({
    label,
    count,
    amount,
    bgColor,
    textColor,
}: {
    label: string;
    count: number;
    amount?: number;
    bgColor: string;
    textColor: string;
}) {
    return (
        <div
            className={`rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border ${bgColor}`}
        >
            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <p className={`mt-2 text-3xl font-bold tabular-nums ${textColor}`}>
                {count}
            </p>
            {amount !== undefined && amount > 0 && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {formatRupiah(amount)}
                </p>
            )}
        </div>
    );
}

function QuickAccessButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex cursor-pointer items-center justify-center rounded-xl border border-sidebar-border/70 p-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground dark:border-sidebar-border"
        >
            {label}
        </button>
    );
}

function QuickAccessLink({ label, href }: { label: string; href: string }) {
    return (
        <Link
            href={href}
            className="flex items-center justify-center rounded-xl border border-sidebar-border/70 p-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground dark:border-sidebar-border"
        >
            {label}
        </Link>
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
            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={`mt-2 text-3xl font-bold tabular-nums ${isPercentage ? 'text-indigo-600 dark:text-indigo-400' : ''}`}
            >
                {isPercentage ? `${value}%` : value}
            </p>
            {isPercentage && (
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-indigo-200 dark:bg-indigo-900/40">
                    <div
                        className="h-full rounded-full bg-indigo-600 transition-all dark:bg-indigo-400"
                        style={{ width: `${value}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function PropertyCard({ property }: { property: PropertyStats }) {
    return (
        <Link
            href={`/properties/${property.slug}`}
            className="block rounded-xl border border-sidebar-border/70 p-5 transition-colors hover:bg-accent dark:border-sidebar-border"
        >
            <h3 className="truncate text-sm font-semibold">{property.name}</h3>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <p className="text-xs text-muted-foreground">Occupied</p>
                    <p className="mt-0.5 text-lg font-bold text-blue-600 tabular-nums dark:text-blue-400">
                        {property.occupied_units}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Available</p>
                    <p className="mt-0.5 text-lg font-bold text-green-600 tabular-nums dark:text-green-400">
                        {property.available_units}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Maintenance</p>
                    <p className="mt-0.5 text-lg font-bold text-amber-600 tabular-nums dark:text-amber-400">
                        {property.maintenance_units}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Occupancy</p>
                    <p className="mt-0.5 text-lg font-bold text-indigo-600 tabular-nums dark:text-indigo-400">
                        {property.occupancy_percentage}%
                    </p>
                </div>
            </div>

            <div className="mt-4">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {property.occupied_units} / {property.total_units} units
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
        </Link>
    );
}

function relativeTime(iso: string): string {
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) {
        return 'just now';
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }

    if (diff < 2592000) {
        return `${Math.floor(diff / 86400)}d ago`;
    }

    return `${Math.floor(diff / 2592000)}mo ago`;
}

Overview.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
