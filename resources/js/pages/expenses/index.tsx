import { Head } from '@inertiajs/react';
import { Ban, EllipsisVertical, Eye, FileText, Pencil } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { ExpenseDetailSheet, ExpenseFormSheet } from '@/components/features';
import { Heading } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useTable } from '@/hooks/use-table';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import expenses from '@/routes/expenses';
import type {
    Expense,
    ExpenseCategory,
    MoneyAggregate,
    PaginatedData,
    TableMeta,
} from '@/types';

type PageProps = {
    expenses: PaginatedData<Expense>;
    properties: { id: number; name: string }[];
    categories: ExpenseCategory[];
    currencies: string[];
    can: { create: boolean; update: boolean; delete: boolean };
    table: TableMeta;
    sort?: string;
    search?: string;
    per_page?: number | string;
    property_id?: string;
    category_id?: string;
    currency?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    summary?: {
        has_data: boolean;
        this_month: MoneyAggregate[];
        last_month: MoneyAggregate[];
        this_month_count: number;
    };
};

function formatMoneyGroups(groups: MoneyAggregate[]): string {
    return (
        groups
            .map((group) => formatPrice(group.amount, group.currency))
            .join(' · ') || '—'
    );
}

export default function Index({
    expenses: data,
    properties,
    categories,
    currencies,
    can,
    table: tableMeta,
    sort: currentSort = '-expense_date',
    search: currentSearch = '',
    per_page: currentPerPage = 15,
    property_id: currentPropertyId = '',
    category_id: currentCategoryId = '',
    currency: currentCurrency = '',
    status: currentStatus = 'active',
    date_from: currentDateFrom = '',
    date_to: currentDateTo = '',
    summary,
}: PageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingExpense, setViewingExpense] = useState<Expense | null>(null);

    const table = useTable({
        routeFn: () => expenses.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            property_id: currentPropertyId,
            category_id: currentCategoryId,
            currency: currentCurrency,
            status: currentStatus,
            date_from: currentDateFrom,
            date_to: currentDateTo,
        },
        defaults: {
            sort: '-expense_date',
            per_page: '15',
            status: 'active',
        },
    });

    function openCreate() {
        setEditingExpense(null);
        setFormOpen(true);
    }

    function openEdit(expense: Expense) {
        setEditingExpense(expense);
        setDetailOpen(false);
        setFormOpen(true);
    }

    function openDetail(expense: Expense) {
        setViewingExpense(expense);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (viewingExpense) {
            openEdit(viewingExpense);
        }
    }

    const columns: TableColumn<Expense>[] = [
        {
            key: 'expense_date',
            label: t('Date'),
            sortable: true,
            render: (expense) => formatDate(expense.expense_date),
        },
        {
            key: 'property_name',
            label: t('Property'),
            sortable: true,
            render: (expense) => expense.property?.name ?? '—',
        },
        {
            key: 'category_name',
            label: t('Category'),
            sortable: true,
            render: (expense) => expense.category?.label ?? '—',
        },
        {
            key: 'vendor',
            label: t('Vendor'),
            render: (expense) => expense.vendor ?? '—',
        },
        {
            key: 'amount',
            label: t('Amount'),
            sortable: true,
            className: 'text-right tabular-nums',
            render: (expense) => formatPrice(expense.amount, expense.currency),
        },
        {
            key: 'status',
            label: t('Status'),
            render: (expense) => (
                <StatusBadge domain="expense" value={expense.status} />
            ),
        },
        {
            key: 'receipt',
            label: t('Receipt'),
            className: 'text-center',
            render: (expense) =>
                expense.receipt ? (
                    <a
                        href={expense.receipt.download_url}
                        target="_blank"
                        rel="noreferrer"
                        onClick={(event) => event.stopPropagation()}
                        className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label={t('View receipt')}
                    >
                        <FileText className="size-4" />
                        <span className="sr-only">{t('View receipt')}</span>
                    </a>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: '_actions',
            label: '',
            render: (expense) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(event) => event.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <DropdownMenuItem onClick={() => openDetail(expense)}>
                            <Eye className="size-4" />
                            {t('View')}
                        </DropdownMenuItem>
                        {expense.status !== 'voided' && can.update && (
                            <DropdownMenuItem onClick={() => openEdit(expense)}>
                                <Pencil className="size-4" />
                                {t('Edit')}
                            </DropdownMenuItem>
                        )}
                        {expense.status !== 'voided' && can.delete && (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => openDetail(expense)}
                            >
                                <Ban className="size-4" />
                                {t('Void')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const dateFilters = (
        <>
            <div className="grid gap-1.5">
                <label
                    htmlFor="date_from"
                    className="text-xs font-medium text-muted-foreground"
                >
                    {t('From')}
                </label>
                <Input
                    id="date_from"
                    type="date"
                    className="w-36"
                    value={currentDateFrom}
                    onChange={(event) =>
                        table.navigate({
                            date_from: event.target.value,
                            page: '',
                        })
                    }
                />
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor="date_to"
                    className="text-xs font-medium text-muted-foreground"
                >
                    {t('To')}
                </label>
                <Input
                    id="date_to"
                    type="date"
                    className="w-36"
                    value={currentDateTo}
                    onChange={(event) =>
                        table.navigate({
                            date_to: event.target.value,
                            page: '',
                        })
                    }
                />
            </div>
        </>
    );

    const dateFilterChips = [
        ...(currentDateFrom
            ? [
                  {
                      key: 'date_from',
                      display: `${t('From')}: ${currentDateFrom}`,
                      onRemove: () =>
                          table.navigate({ date_from: '', page: '' }),
                  },
              ]
            : []),
        ...(currentDateTo
            ? [
                  {
                      key: 'date_to',
                      display: `${t('To')}: ${currentDateTo}`,
                      onRemove: () => table.navigate({ date_to: '', page: '' }),
                  },
              ]
            : []),
    ];
    const activeFilters = Object.fromEntries(
        Object.entries(table.activeFilters).filter(
            ([key]) => key !== 'date_from' && key !== 'date_to',
        ),
    );

    return (
        <>
            <Head title={t('Expenses')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('Expenses')}
                        description={t(
                            'Track operating costs across your properties',
                        )}
                    />
                    {can.create && (
                        <Button onClick={openCreate}>{t('New Expense')}</Button>
                    )}
                </div>

                {summary?.has_data && (
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="min-w-0 rounded-lg border bg-card px-4 py-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                {t('Expenses this month')}
                            </p>
                            <p className="mt-1 truncate text-lg font-semibold tabular-nums">
                                {formatMoneyGroups(summary.this_month)}
                            </p>
                        </div>
                        <div className="min-w-0 rounded-lg border bg-card px-4 py-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                {t('Expenses last month')}
                            </p>
                            <p className="mt-1 truncate text-lg font-semibold tabular-nums">
                                {formatMoneyGroups(summary.last_month)}
                            </p>
                        </div>
                        <div className="min-w-0 rounded-lg border bg-card px-4 py-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                {t('Expense count this month')}
                            </p>
                            <p className="mt-1 text-lg font-semibold tabular-nums">
                                {summary.this_month_count}
                            </p>
                        </div>
                    </div>
                )}

                <FilterBar
                    filters={tableMeta.filters}
                    activeFilters={activeFilters}
                    activeFilterCount={table.activeFilterCount}
                    onToggleOption={table.toggleFilterOption}
                    onClearAll={table.clearAllFilters}
                    additionalFilters={dateFilters}
                    additionalFilterChips={dateFilterChips}
                    searchInput={
                        <SearchInput
                            value={table.searchValue}
                            onChange={table.onSearchChange}
                            onClear={table.clearSearch}
                            placeholder={t(
                                'Search by vendor, property, category, or reference...',
                            )}
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={openDetail}
                    paginator={data}
                    perPage={Number(currentPerPage)}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun={t('expenses')}
                    empty={{
                        message: t('No expenses yet.'),
                        createLabel: can.create
                            ? t('Record your first expense')
                            : undefined,
                        onCreate: can.create ? openCreate : undefined,
                    }}
                />
            </div>

            <ExpenseDetailSheet
                expense={viewingExpense}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                canUpdate={can.update}
                canDelete={can.delete}
                onEdit={editFromDetail}
            />

            <ExpenseFormSheet
                key={editingExpense?.id ?? 'new'}
                expense={editingExpense}
                properties={properties}
                categories={categories}
                currencies={currencies}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Expenses', href: expenses.index() }],
};
