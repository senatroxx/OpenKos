import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Building2,
    CalendarClock,
    ChevronDown,
    Clock,
    FileText,
    UserCheck,
    UserPlus,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import {
    ActivityFeedItem,
    BusinessHealthPanel,
    CurrencyAmountList,
    getActivitySummaryChips,
    OperationalBriefingCard,
    PropertyFormSheet,
    PropertyOverviewCard,
    TenantFormSheet,
    TicketFormSheet,
} from '@/components/features';
import { MetricCard } from '@/components/shared/metric-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import type {
    AttentionData,
    Finance,
    MaintenanceProperty,
    MaintenanceUnit,
    PropertyStats,
    RecentActivityEntry,
    Stats,
} from '@/types';

export default function Overview({
    attention,
    finance,
    stats,
    recent_activity,
    properties,
    units,
}: {
    attention: AttentionData;
    finance: Finance;
    stats: Stats;
    recent_activity: RecentActivityEntry[];
    properties: MaintenanceProperty[];
    units: MaintenanceUnit[];
}) {
    const [tenantSheetOpen, setTenantSheetOpen] = useState(false);
    const [propertySheetOpen, setPropertySheetOpen] = useState(false);
    const [ticketSheetOpen, setTicketSheetOpen] = useState(false);

    const activitySummaryChips = getActivitySummaryChips(recent_activity);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col overflow-x-auto p-4 md:p-6 lg:p-8">
                {/* 1. Page Header with Aligned Quick Actions */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Dashboard
                        </h1>
                        <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                            Monitor billing, occupancy, and property operations.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="default"
                            size="sm"
                            onClick={() => setTenantSheetOpen(true)}
                            className="cursor-pointer gap-2 shadow-xs"
                        >
                            <UserPlus className="size-4" />
                            Add Tenant
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="gap-2 bg-card shadow-xs"
                        >
                            <Link href="/dashboard/rent">
                                <Banknote className="size-4 text-muted-foreground" />
                                Collect Rent
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="cursor-pointer gap-1.5 bg-card shadow-xs"
                                >
                                    More
                                    <ChevronDown className="size-3.5 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => setTicketSheetOpen(true)}
                                >
                                    <Wrench className="mr-2 size-4 text-muted-foreground" />
                                    Report Maintenance
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setPropertySheetOpen(true)}
                                >
                                    <Building2 className="mr-2 size-4 text-muted-foreground" />
                                    Add Property
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href="/tenants">
                                        <UserCheck className="mr-2 size-4 text-muted-foreground" />
                                        Assign Tenant
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* 2. Visual Hero — Operational Briefing Callout Card */}
                <OperationalBriefingCard attention={attention} />

                {/* 3. Today's Attention Metrics */}
                <section className="mb-10 flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Today&apos;s Attention
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <MetricCard
                            label="Overdue Invoices"
                            value={attention.overdue_invoices.count}
                            subtext={
                                attention.overdue_invoices.amounts.length >
                                0 ? (
                                    <CurrencyAmountList
                                        groups={
                                            attention.overdue_invoices.amounts
                                        }
                                        amountClassName="text-surface-red-foreground"
                                    />
                                ) : undefined
                            }
                            variant="red"
                            emphasis="attention"
                            icon={AlertTriangle}
                        />
                        <MetricCard
                            label="Due Today"
                            value={attention.due_today}
                            variant="amber"
                            emphasis="subtle"
                            icon={CalendarClock}
                        />
                        <MetricCard
                            label="Open Maintenance"
                            value={attention.open_maintenance}
                            variant="amber"
                            emphasis="subtle"
                            icon={Wrench}
                        />
                        <MetricCard
                            label="Leases Ending Soon"
                            value={attention.leases_ending_soon}
                            variant="blue"
                            emphasis="subtle"
                            icon={FileText}
                        />
                        <MetricCard
                            label="Pending Review"
                            value={attention.pending_payment_verification}
                            variant="purple"
                            emphasis="subtle"
                            icon={Clock}
                        />
                    </div>
                </section>

                {/* 4. Business Health Neutral Panel */}
                <BusinessHealthPanel finance={finance} />

                {/* 5. Lower Dashboard: Operational Workspace (Two-Column Layout) */}
                <div className="grid gap-8 lg:grid-cols-12">
                    {/* Left Column: Property Overview (~65% / lg:col-span-7) */}
                    <section className="flex flex-col gap-3 lg:col-span-7">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Property Overview
                            </h2>
                            {stats.properties.length > 0 && (
                                <Link
                                    href="/properties"
                                    className="inline-flex items-center gap-1 text-xs font-medium text-primary transition-colors hover:underline"
                                >
                                    View All Properties (
                                    {stats.properties.length}) →
                                </Link>
                            )}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {stats.properties
                                .slice(0, 6)
                                .map((property: PropertyStats) => (
                                    <PropertyOverviewCard
                                        key={property.id}
                                        property={property}
                                    />
                                ))}
                        </div>
                    </section>

                    {/* Right Column: Recent Activity Timeline (~35% / lg:col-span-5) */}
                    <section className="flex flex-col gap-3 lg:col-span-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Recent Activity
                            </h2>
                            <span className="text-xs font-medium text-muted-foreground tabular-nums">
                                {recent_activity.length} events
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-4 shadow-2xs">
                            {/* Category Chips Summary Bar */}
                            {activitySummaryChips.length > 0 && (
                                <div className="mb-4 flex flex-wrap items-center gap-1.5 border-b border-border/50 pb-3">
                                    {activitySummaryChips.map((chip) => (
                                        <span
                                            key={chip.label}
                                            className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            <span>{chip.label}</span>
                                            <span className="font-bold text-foreground tabular-nums">
                                                {chip.count}
                                            </span>
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* Operational Feed Items */}
                            {recent_activity.length > 0 ? (
                                <div className="space-y-3.5">
                                    {recent_activity.map((entry) => (
                                        <ActivityFeedItem
                                            key={entry.id}
                                            entry={entry}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No recent activity recorded.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
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

Overview.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
