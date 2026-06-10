'use client'

import { Check, ChevronsUpDown, SearchIcon } from 'lucide-react'
import * as React from 'react'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

export type Option = {
    value: string | number
    label: string
}

type SearchableSelectProps = {
    options: Option[]
    value?: string | number | null
    onChange: (value: string | number | null) => void
    placeholder?: string
    searchPlaceholder?: string
    emptyText?: string
    disabled?: boolean
}

export default function SearchableSelect({
    options,
    value,
    onChange,
    placeholder = 'Select...',
    searchPlaceholder = 'Search...',
    emptyText = 'No results found.',
    disabled = false,
}: SearchableSelectProps) {
    const [open, setOpen] = React.useState(false)
    const [search, setSearch] = React.useState('')

    const selected = options.find((o) => String(o.value) === String(value))

    const filtered = React.useMemo(
        () =>
            options.filter((o) =>
                o.label.toLowerCase().includes(search.toLowerCase()),
            ),
        [options, search],
    )

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className="w-full justify-between font-normal"
                >
                    {selected ? (
                        selected.label
                    ) : (
                        <span className="text-muted-foreground">
                            {placeholder}
                        </span>
                    )}
                    <ChevronsUpDown
                        data-icon="inline-end"
                        className="shrink-0 opacity-50"
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0">
                <div className="flex items-center border-b px-3">
                    <SearchIcon className="mr-2 size-4 shrink-0 opacity-50" />
                    <Input
                        placeholder={searchPlaceholder}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-10 border-0 bg-transparent shadow-none focus-visible:ring-0"
                    />
                </div>
                <div style={{ height: '200px', overflowY: 'scroll' }} className="p-1">
                    {filtered.length === 0 ? (
                        <div className="py-6 text-center text-sm text-muted-foreground">
                            {emptyText}
                        </div>
                    ) : (
                        filtered.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                className={cn(
                                    'relative flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 pr-8 text-sm outline-hidden select-none hover:bg-accent hover:text-accent-foreground',
                                    String(value) === String(option.value) &&
                                        'bg-accent text-accent-foreground',
                                )}
                                onClick={() => {
                                    onChange(
                                        option.value === value
                                            ? null
                                            : option.value,
                                    )
                                    setOpen(false)
                                }}
                            >
                                <Check
                                    className={cn(
                                        'mr-2 size-4',
                                        String(value) ===
                                            String(option.value)
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                />
                                {option.label}
                            </button>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    )
}
