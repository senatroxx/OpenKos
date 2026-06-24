export type PaginationLinks = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
    links: PaginationLinks[];
};

export type TableColumnMeta = {
    key: string;
    label: string;
    sortable: boolean;
    searchable: boolean;
};

export type TableFilterOption = string | { value: string; label: string };

export type TableFilterMeta = {
    key: string;
    label: string;
    type: 'select' | 'toggle';
    options: TableFilterOption[];
};

export type TableMeta = {
    columns: TableColumnMeta[];
    filters: TableFilterMeta[];
};
