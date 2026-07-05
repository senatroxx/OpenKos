import { Head } from '@inertiajs/react';
import { AlertTriangle, Bell, CalendarClock, CheckCircle } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTable } from '@/hooks/use-table';
import { dashboard } from '@/routes';
import { rent as dashboardRent } from '@/routes/dashboard';
import type { PaginatedData, RentDashboardEntry, TableMeta } from '@/types';

type PageProps = {
    entries: PaginatedData<RentDashboardEntry>;
    sort?: string;
    search?: string;
    status?: string;
    properties?: string;
    due_date?: string;
    per_page?: number;
    table: TableMeta;
    stats: {
        overdue: { count: number; amount: number };
        due_today: number;
        due_soon: number;
        paid: number;
    };
};

const DUE_DAY_LABELS: Record<number, string> = {
    1: '1st',
    5: '5th',
    10: '10th',
    15: '15th',
    20: '20th',
    25: '25th',
    31: 'Last day',
};

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
    paid: { label: 'Paid', className: 'bg-green-600 text-white' },
    overdue: { label: 'Overdue', className: 'bg-red-600 text-white' },
    due_today: { label: 'Due Today', className: 'bg-amber-600 text-white' },
    due_soon: { label: 'Due Soon', className: 'bg-blue-600 text-white' },
};

function formatPrice(cents: string): string {
    const num = Number.parseFloat(cents);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}

export default function Rent({
    entries: data,
    sort: currentSort = 'rent_due_day',
    search: currentSearch = '',
    status: currentStatus = '',
    properties: currentProperties = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
    stats,
}: PageProps) {
    const table = useTable({
        routeFn: () => dashboardRent(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
            properties: currentProperties,
        },
        defaults: {
            sort: 'rent_due_day',
            per_page: '15',
        },
    });

    const columns: TableColumn<RentDashboardEntry>[] = [
        {
            key: 'tenant_name',
            label: 'Tenant',
            className: 'font-medium',
            render: (entry) => entry.tenant_name,
        },
        {
            key: 'unit_name',
            label: 'Unit',
            render: (entry) => entry.unit_name,
        },
        {
            key: 'property_name',
            label: 'Property',
            render: (entry) => entry.property_name,
        },
        {
            key: 'rent_due_day',
            label: 'Due Date',
            sortable: true,
            className: 'tabular-nums',
            render: (entry) =>
                DUE_DAY_LABELS[entry.rent_due_day] ?? `${entry.rent_due_day}th`,
        },
        {
            key: 'days_overdue',
            label: 'Days Overdue',
            className: 'tabular-nums',
            render: (entry) =>
                entry.days_overdue !== null
                    ? `${entry.days_overdue} day${entry.days_overdue !== 1 ? 's' : ''}`
                    : '\u2014',
        },
        {
            key: 'rent_amount',
            label: 'Amount Due',
            sortable: true,
            className: 'tabular-nums',
            render: (entry) => formatPrice(entry.rent_amount),
        },
        {
            key: 'rent_status',
            label: 'Status',
            render: (entry) => {
                const badge = STATUS_BADGE[entry.rent_status];

                return <Badge className={badge.className}>{badge.label}</Badge>;
            },
        },
    ];

    return (
        <>
            <Head title="Rent Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                    <Card>
                        <CardContent className="flex items-center gap-4 px-6">
                            <AlertTriangle className="size-10 shrink-0 text-red-600" />
                            <div className="min-w-0">
                                <p className="text-sm text-muted-foreground">
                                    Overdue
                                </p>
                                <p className="truncate text-2xl font-bold text-red-600 tabular-nums">
                                    {stats.overdue.count}
                                </p>
                                {stats.overdue.amount > 0 && (
                                    <p className="truncate text-xs text-muted-foreground tabular-nums">
                                        {formatPrice(
                                            String(stats.overdue.amount),
                                        )}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-4 px-6">
                            <CalendarClock className="size-10 shrink-0 text-amber-600" />
                            <div className="min-w-0">
                                <p className="text-sm text-muted-foreground">
                                    Due Today
                                </p>
                                <p className="truncate text-2xl font-bold text-amber-600 tabular-nums">
                                    {stats.due_today}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-4 px-6">
                            <Bell className="size-10 shrink-0 text-blue-600" />
                            <div className="min-w-0">
                                <p className="text-sm text-muted-foreground">
                                    Due Soon
                                </p>
                                <p className="truncate text-2xl font-bold text-blue-600 tabular-nums">
                                    {stats.due_soon}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-4 px-6">
                            <CheckCircle className="size-10 shrink-0 text-green-600" />
                            <div className="min-w-0">
                                <p className="text-sm text-muted-foreground">
                                    Paid
                                </p>
                                <p className="truncate text-2xl font-bold text-green-600 tabular-nums">
                                    {stats.paid}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <FilterBar
                    filters={tableMeta.filters}
                    activeFilters={table.activeFilters}
                    activeFilterCount={table.activeFilterCount}
                    onToggleOption={table.toggleFilterOption}
                    onClearAll={table.clearAllFilters}
                    searchInput={
                        <SearchInput
                            value={table.searchValue}
                            onChange={table.onSearchChange}
                            onClear={table.clearSearch}
                            placeholder="Search tenant, unit, property..."
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun="entries"
                    empty={{
                        message: 'No outstanding rent entries found.',
                    }}
                />
            </div>
        </>
    );
}

Rent.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Rent',
            href: dashboardRent(),
        },
    ],
};
