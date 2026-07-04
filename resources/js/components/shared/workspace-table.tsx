import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import { useTable } from '@/hooks/use-table';
import type { PaginatedData, TableMeta } from '@/types';

/**
 * Server-driven table with search, filters, sorting, and pagination for
 * workspace tab pages. Pairs with a backend App\Tables\Table::paginate()
 * response spread into the page props.
 */
export function WorkspaceTable<T extends { id: number }>({
    url,
    noun,
    rows,
    columns,
    tableMeta,
    sort,
    search,
    perPage,
    filterValues = {},
    defaultSort,
    searchPlaceholder,
    emptyMessage,
    onRowClick,
}: {
    url: string;
    noun: string;
    rows: PaginatedData<T>;
    columns: TableColumn<T>[];
    tableMeta: TableMeta;
    sort: string;
    search: string;
    perPage: number;
    filterValues?: Record<string, string>;
    defaultSort: string;
    searchPlaceholder: string;
    emptyMessage: string;
    onRowClick?: (row: T) => void;
}) {
    const table = useTable({
        routeFn: () => ({ url }),
        params: {
            sort,
            search,
            per_page: String(perPage),
            ...filterValues,
        },
        defaults: {
            sort: defaultSort,
            per_page: '15',
        },
    });

    return (
        <div className="flex flex-col gap-4">
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
                        placeholder={searchPlaceholder}
                    />
                }
            />

            <DataTable
                columns={columns}
                rows={rows.data}
                currentSort={sort}
                onSort={table.toggleSort}
                onRowClick={onRowClick}
                paginator={rows}
                perPage={perPage}
                onPageChange={table.goToPage}
                onPerPageChange={table.setPerPage}
                noun={noun}
                empty={{ message: emptyMessage }}
            />
        </div>
    );
}
