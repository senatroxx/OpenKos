import { Head, router } from '@inertiajs/react';
import { EllipsisVertical, Pause, Pencil, Play, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { RecurringExpenseFormSheet } from '@/components/features';
import { Heading } from '@/components/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { formatBillingPeriod, formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import recurringExpenses from '@/routes/expenses/recurring';
import type { ExpenseCategory, PaginatedData, RecurringExpense, TableMeta } from '@/types';

type PageProps = {
    recurring_expenses: PaginatedData<RecurringExpense>;
    properties: { id: number; name: string }[];
    categories: ExpenseCategory[];
    currencies: string[];
    can: { create: boolean; update: boolean };
    table: TableMeta;
    sort?: string;
    search?: string;
    per_page?: number | string;
    status?: string;
};

function statusBadge(status: string) {
    const labels: Record<string, string> = {
        active: 'Active',
        paused: 'Paused',
        ended: 'Ended',
    };

    return <Badge variant={status === 'active' ? 'default' : 'secondary'}>{t(labels[status] ?? status)}</Badge>;
}

export default function Recurring({
    recurring_expenses: data,
    properties,
    categories,
    currencies,
    can,
    table: tableMeta,
    sort: currentSort = '-start_date',
    search: currentSearch = '',
    per_page: currentPerPage = 15,
    status: currentStatus = '',
}: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<RecurringExpense | null>(null);
    const table = useTable({
        routeFn: () => recurringExpenses.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
        },
        defaults: { sort: '-start_date', per_page: '15' },
    });

    function openCreate(): void {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(recurringExpense: RecurringExpense): void {
        setEditing(recurringExpense);
        setFormOpen(true);
    }

    function pause(recurringExpense: RecurringExpense): void {
        router.post(recurringExpenses.pause.url(recurringExpense));
    }

    function resume(recurringExpense: RecurringExpense): void {
        router.post(recurringExpenses.resume.url(recurringExpense));
    }

    const columns: TableColumn<RecurringExpense>[] = [
        {
            key: 'property_name',
            label: t('Property'),
            sortable: true,
            render: (item) => item.property?.name ?? '—',
        },
        {
            key: 'category_name',
            label: t('Category'),
            sortable: true,
            render: (item) => item.category?.label ?? '—',
        },
        {
            key: 'amount',
            label: t('Amount'),
            sortable: true,
            className: 'text-right tabular-nums',
            render: (item) => formatPrice(item.amount, item.currency),
        },
        {
            key: 'billing_unit',
            label: t('Frequency'),
            sortable: true,
            render: (item) => formatBillingPeriod(item.billing_interval, item.billing_unit),
        },
        {
            key: 'start_date',
            label: t('Start'),
            sortable: true,
            render: (item) => formatDate(item.start_date),
        },
        {
            key: 'end_date',
            label: t('End'),
            sortable: true,
            render: (item) => formatDate(item.end_date),
        },
        {
            key: 'status',
            label: t('Status'),
            render: (item) => statusBadge(item.status),
        },
        {
            key: '_actions',
            label: '',
            render: (item) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild onClick={(event) => event.stopPropagation()}>
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" onClick={(event) => event.stopPropagation()}>
                        {can.update && item.status !== 'ended' && (
                            <DropdownMenuItem onClick={() => openEdit(item)}>
                                <Pencil className="size-4" />
                                {t('Edit')}
                            </DropdownMenuItem>
                        )}
                        {can.update && item.status === 'active' && (
                            <DropdownMenuItem onClick={() => pause(item)}>
                                <Pause className="size-4" />
                                {t('Pause')}
                            </DropdownMenuItem>
                        )}
                        {can.update && item.status === 'paused' && (
                            <DropdownMenuItem onClick={() => resume(item)}>
                                <Play className="size-4" />
                                {t('Resume')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const activeFilters = table.activeFilters;

    return (
        <>
            <Head title={t('Recurring Expenses')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('Recurring Expenses')}
                        description={t('Automatically generate operating expenses on a schedule.')}
                    />
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            {t('New recurring expense')}
                        </Button>
                    )}
                </div>

                <FilterBar
                    filters={tableMeta.filters}
                    activeFilters={activeFilters}
                    activeFilterCount={table.activeFilterCount}
                    onToggleOption={table.toggleFilterOption}
                    onClearAll={table.clearAllFilters}
                    searchInput={
                        <SearchInput
                            value={table.searchValue}
                            onChange={table.onSearchChange}
                            onClear={table.clearSearch}
                            placeholder={t('Search by property, category, or vendor...')}
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    paginator={data}
                    perPage={Number(currentPerPage)}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun={t('recurring expenses')}
                    empty={{
                        message: t('No recurring expenses yet.'),
                        createLabel: can.create ? t('Add a recurring expense') : undefined,
                        onCreate: can.create ? openCreate : undefined,
                    }}
                />
            </div>

            <RecurringExpenseFormSheet
                recurringExpense={editing}
                properties={properties}
                categories={categories}
                currencies={currencies}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
        </>
    );
}

Recurring.layout = {
    breadcrumbs: [{ title: 'Recurring Expenses', href: recurringExpenses.index() }],
};
