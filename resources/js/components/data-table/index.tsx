import type { ReactNode } from 'react';
import { DataTablePagination } from '@/components/data-table/pagination';
import { SortHeader } from '@/components/data-table/sort-header';
import { EmptyState } from '@/components/shared';
import type { PaginatedData } from '@/types';

export type TableColumn<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    className?: string;
    render?: (row: T) => ReactNode;
};

type DataTableProps<T> = {
    columns: TableColumn<T>[];
    rows: T[];
    currentSort: string;
    onSort: (column: string) => void;
    onRowClick?: (row: T) => void;
    paginator: PaginatedData<T>;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    noun: string;
    rowKey?: (row: T) => string | number;
    empty?: {
        message: string;
        createLabel?: string;
        onCreate?: () => void;
    };
};

export function DataTable<T>({
    columns,
    rows,
    currentSort,
    onSort,
    onRowClick,
    paginator,
    perPage,
    onPageChange,
    onPerPageChange,
    noun,
    rowKey,
    empty,
}: DataTableProps<T>) {
    return (
        <>
            {rows.length === 0 && empty ? (
                <EmptyState
                    message={empty.message}
                    createLabel={empty.createLabel}
                    onCreate={empty.onCreate}
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                {columns.map((col) =>
                                    col.sortable ? (
                                        <SortHeader
                                            key={col.key}
                                            column={col.key}
                                            label={col.label}
                                            currentSort={currentSort}
                                            onToggle={onSort}
                                        />
                                    ) : (
                                        <th
                                            key={col.key}
                                            className={`px-4 py-3 font-medium ${col.className ?? ''}`}
                                        >
                                            {col.label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => {
                                const id =
                                    rowKey?.(row) ??
                                    ((row as Record<string, unknown>).id as
                                        | string
                                        | number
                                        | undefined) ??
                                    i;

                                return (
                                    <tr
                                        key={id}
                                        className={`border-b last:border-0 hover:bg-muted/30 ${onRowClick ? 'cursor-pointer' : ''}`}
                                        onClick={() => onRowClick?.(row)}
                                    >
                                        {columns.map((col) => (
                                            <td
                                                key={col.key}
                                                className={`px-4 py-3 ${col.className ?? ''}`}
                                            >
                                                {col.render
                                                    ? col.render(row)
                                                    : String(
                                                          (
                                                              row as Record<
                                                                  string,
                                                                  unknown
                                                              >
                                                          )[col.key] ?? '',
                                                      )}
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    <DataTablePagination
                        data={paginator}
                        perPage={perPage}
                        onPageChange={onPageChange}
                        onPerPageChange={onPerPageChange}
                        noun={noun}
                    />
                </div>
            )}
        </>
    );
}
