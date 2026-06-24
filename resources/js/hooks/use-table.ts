import { router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';

type Params = Record<string, string>;

type UseTableOptions = {
    routeFn: () => { url: string };
    params: Record<string, string>;
    defaults?: Record<string, string>;
};

export function useTable({ routeFn, params, defaults = {} }: UseTableOptions) {
    const [searchValue, setSearchValue] = useState(params.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const url = routeFn().url;

    const navigate = useCallback(
        (overrides: Params) => {
            const merged: Params = { ...params, ...overrides };

            const cleaned: Params = {};

            for (const [key, value] of Object.entries(merged)) {
                if (value !== '' && defaults[key] !== value) {
                    cleaned[key] = value;
                }
            }

            router.get(url, cleaned, {
                preserveState: true,
                replace: true,
            });
        },
        [params, url, defaults],
    );

    const onSearchChange = useCallback(
        (value: string) => {
            setSearchValue(value);

            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(() => {
                const merged: Params = { ...params, search: value, page: '' };

                const cleaned: Params = {};

                for (const [key, v] of Object.entries(merged)) {
                    if (v !== '' && defaults[key] !== v) {
                        cleaned[key] = v;
                    }
                }

                router.get(url, cleaned, {
                    preserveState: true,
                    replace: true,
                });
            }, 300);
        },
        [params, url, defaults],
    );

    function clearSearch() {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        setSearchValue('');
        navigate({ search: '', page: '' });
    }

    function toggleSort(column: string) {
        const current = params.sort ?? '';

        const newSort = current === column
            ? `-${column}`
            : current === `-${column}`
                ? column
                : column;

        navigate({ sort: newSort, page: '' });
    }

    function goToPage(page: number) {
        navigate({ page: String(page) });
    }

    function setPerPage(perPage: number) {
        navigate({ per_page: String(perPage), page: '' });
    }

    function toggleFilterOption(key: string, value: string) {
        const current = params[key] ?? '';
        const parts = current ? current.split(',') : [];

        const idx = parts.indexOf(value);
        const next =
            idx === -1
                ? [...parts, value].join(',')
                : parts.filter((p) => p !== value).join(',');

        navigate({ [key]: next, page: '' });
    }

    function clearAllFilters() {
        const cleared: Params = { page: '' };

        for (const key of Object.keys(params)) {
            if (
                key === 'sort' ||
                key === 'search' ||
                key === 'per_page' ||
                key === 'page'
            ) {
                continue;
            }

            cleared[key] = '';
        }

        navigate(cleared);
    }

    const filterKeys = Object.keys(params).filter(
        (k) =>
            k !== 'sort' && k !== 'search' && k !== 'per_page' && k !== 'page',
    );

    const activeFilterCount = filterKeys.filter(
        (k) => params[k] && params[k] !== '',
    ).length;

    const activeFilters: Params = {};

    for (const k of filterKeys) {
        if (params[k] && params[k] !== '') {
            activeFilters[k] = params[k];
        }
    }

    return {
        searchValue,
        onSearchChange,
        clearSearch,
        toggleSort,
        goToPage,
        setPerPage,
        toggleFilterOption,
        clearAllFilters,
        activeFilterCount,
        activeFilters,
    };
}
