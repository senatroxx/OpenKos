import { Check, ChevronsUpDown, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { TableFilterMeta } from '@/types';

type FilterBarProps = {
    filters: TableFilterMeta[];
    activeFilters: Record<string, string>;
    activeFilterCount: number;
    onToggleOption: (key: string, value: string) => void;
    onClearAll: () => void;
    searchInput: ReactNode;
};

function optLabel(filter: TableFilterMeta, value: string): string | undefined {
    if (filter.type === 'toggle') {
        return value === '1' ? filter.label : undefined;
    }

    const option = filter.options.find((o) => {
        if (typeof o === 'string') {
            return o === value;
        }

        return o.value === value;
    });

    if (typeof option === 'string') {
        return option;
    }

    return option?.label;
}

function SelectFilter({
    filter,
    selected,
    onToggle,
}: {
    filter: TableFilterMeta;
    selected: string[];
    onToggle: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);

    const selectedLabels = selected
        .map((v) => optLabel(filter, v))
        .filter(Boolean)
        .join(', ');

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between font-normal md:w-48"
                >
                    <span className="truncate">
                        {selectedLabels || `All ${filter.label.toLowerCase()}`}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput
                        placeholder={`Search ${filter.label.toLowerCase()}...`}
                    />
                    <CommandList>
                        <CommandEmpty>No options found.</CommandEmpty>
                        <CommandGroup>
                            {filter.options.map((opt) => {
                                const optValue =
                                    typeof opt === 'string' ? opt : opt.value;
                                const optLabel =
                                    typeof opt === 'string' ? opt : opt.label;

                                return (
                                    <CommandItem
                                        key={optValue}
                                        value={optLabel}
                                        onSelect={() => {
                                            onToggle(optValue);
                                        }}
                                    >
                                        <Check
                                            className={`mr-2 size-4 ${
                                                selected.includes(optValue)
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            }`}
                                        />
                                        {optLabel}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

export function FilterBar({
    filters,
    activeFilters,
    activeFilterCount,
    onToggleOption,
    onClearAll,
    searchInput,
}: FilterBarProps) {
    const [open, setOpen] = useState(false);

    const filterChips = Object.entries(activeFilters).flatMap(
        ([key, value]) => {
            const filter = filters.find((f) => f.key === key);
            const label = filter?.label ?? key;
            const values = value.split(',');

            return values.map((v) => ({
                key: `${key}-${v}`,
                filterKey: key,
                value: v,
                display: `${label}: ${optLabel(filter!, v) ?? v}`,
            }));
        },
    );

    const selectedValues = (key: string): string[] =>
        activeFilters[key] ? activeFilters[key].split(',') : [];

    return (
        <div>
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0 flex-1">{searchInput}</div>

                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant={open ? 'default' : 'outline'}
                        size="icon"
                        className="relative shrink-0"
                        onClick={() => setOpen((v) => !v)}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            className="lucide lucide-funnel-icon lucide-funnel size-4"
                        >
                            <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z" />
                        </svg>
                        {activeFilterCount > 0 && (
                            <span className="absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground">
                                {activeFilterCount}
                            </span>
                        )}
                    </Button>
                </div>
            </div>

            {open && (
                <div className="mt-3 rounded-lg border bg-card p-4">
                    <div className="flex flex-wrap gap-3">
                        {filters.map((filter) => {
                            if (filter.type === 'select') {
                                return (
                                    <div key={filter.key} className="min-w-0">
                                        <p className="mb-1.5 text-xs font-medium tracking-wider text-muted-foreground">
                                            {filter.label}
                                        </p>
                                        <SelectFilter
                                            filter={filter}
                                            selected={selectedValues(
                                                filter.key,
                                            )}
                                            onToggle={(value) =>
                                                onToggleOption(
                                                    filter.key,
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                );
                            }

                            if (filter.type === 'toggle') {
                                return (
                                    <div key={filter.key} className="min-w-0">
                                        <p className="mb-1.5 text-xs font-medium tracking-wider text-muted-foreground">
                                            {filter.label}
                                        </p>
                                        <Button
                                            variant={
                                                selectedValues(
                                                    filter.key,
                                                ).includes('1')
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                onToggleOption(filter.key, '1')
                                            }
                                        >
                                            {selectedValues(
                                                filter.key,
                                            ).includes('1')
                                                ? filter.label
                                                : `Show ${filter.label}`}
                                        </Button>
                                    </div>
                                );
                            }

                            return null;
                        })}
                    </div>
                </div>
            )}

            {filterChips.length > 0 && (
                <div className="mt-3 flex items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1">
                        {filterChips.map((chip) => (
                            <Badge
                                key={chip.key}
                                variant="secondary"
                                className="gap-1"
                            >
                                {chip.display}
                                <button
                                    onClick={() =>
                                        onToggleOption(
                                            chip.filterKey,
                                            chip.value,
                                        )
                                    }
                                    className="ml-0.5 hover:text-foreground"
                                >
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        ))}
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onClearAll}
                        className="shrink-0 text-xs"
                    >
                        Clear all
                    </Button>
                </div>
            )}
        </div>
    );
}
