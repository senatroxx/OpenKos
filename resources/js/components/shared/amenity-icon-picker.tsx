import { Check, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Command, CommandInput, CommandList } from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    AmenityIcon,
    amenityIconOptions,
    getAmenityIconLabel,
} from '@/lib/amenity-icons';
import type { AmenityIconName } from '@/lib/amenity-icons';
import { t } from '@/lib/i18n';

type AmenityIconOption = (typeof amenityIconOptions)[number];

function IconGrid({
    label,
    options,
    selected,
    onSelect,
}: {
    label: string;
    options: AmenityIconOption[];
    selected: string | null;
    onSelect: (value: AmenityIconName) => void;
}) {
    const gridRef = useRef<HTMLDivElement>(null);
    const buttonRefs = useRef<(HTMLButtonElement | null)[]>([]);

    function moveFocus(
        event: React.KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) {
        const grid = gridRef.current;
        const columns = grid
            ? Math.max(
                  1,
                  window
                      .getComputedStyle(grid)
                      .gridTemplateColumns.split(' ')
                      .filter(Boolean).length,
              )
            : 1;
        let nextIndex = index;

        if (event.key === 'ArrowRight') {
            nextIndex = index + 1;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = index - 1;
        } else if (event.key === 'ArrowDown') {
            nextIndex = index + columns;
        } else if (event.key === 'ArrowUp') {
            nextIndex = index - columns;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = options.length - 1;
        } else {
            return;
        }

        if (nextIndex < 0 || nextIndex >= options.length) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        buttonRefs.current[nextIndex]?.focus();
    }

    return (
        <div
            ref={gridRef}
            role="group"
            aria-label={label}
            className="grid grid-cols-5 gap-1.5 min-[22rem]:grid-cols-6 sm:grid-cols-7"
        >
            {options.map((option, index) => {
                const isSelected = selected === option.value;

                return (
                    <Tooltip key={option.value}>
                        <TooltipTrigger asChild>
                            <button
                                ref={(element) => {
                                    buttonRefs.current[index] = element;
                                }}
                                type="button"
                                aria-label={t(option.label)}
                                aria-pressed={isSelected}
                                title={t(option.label)}
                                className={`relative flex aspect-square min-h-12 w-full items-center justify-center rounded-md border border-transparent text-muted-foreground transition-colors hover:border-border hover:bg-accent hover:text-accent-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 ${isSelected ? 'border-primary bg-accent text-accent-foreground' : ''}`}
                                onClick={() => onSelect(option.value)}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        onSelect(option.value);

                                        return;
                                    }

                                    moveFocus(event, index);
                                }}
                            >
                                <AmenityIcon
                                    icon={option.value}
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">
                                    {t(option.label)}
                                </span>
                                {isSelected && (
                                    <Check
                                        className="absolute top-1 right-1 size-3"
                                        aria-hidden="true"
                                    />
                                )}
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {t(option.label)}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}

export default function AmenityIconPicker({
    value,
    onChange,
}: {
    value: string | null;
    onChange: (value: AmenityIconName | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const triggerRef = useRef<HTMLButtonElement>(null);
    const selected = amenityIconOptions.find(
        (option) => option.value === value,
    );
    const triggerLabel = selected
        ? t(`Change icon, currently ${selected.label}`)
        : value
          ? t(`Change icon, currently ${getAmenityIconLabel(value)}`)
          : t('Choose an icon');
    const normalizedSearch = search.trim().toLocaleLowerCase();
    const filteredOptions = normalizedSearch
        ? amenityIconOptions.filter((option) =>
              option.searchText.includes(normalizedSearch),
          )
        : amenityIconOptions;
    const commonOptions = amenityIconOptions.filter(
        (option) => 'common' in option && option.common === true,
    );

    function handleOpenChange(nextOpen: boolean) {
        setOpen(nextOpen);

        if (!nextOpen) {
            setSearch('');
        }
    }

    function selectIcon(icon: AmenityIconName | null) {
        onChange(icon);
        setSearch('');
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    ref={triggerRef}
                    id="amenity-icon"
                    type="button"
                    variant="outline"
                    size="icon"
                    role="combobox"
                    aria-expanded={open}
                    aria-label={triggerLabel}
                    title={triggerLabel}
                >
                    <AmenityIcon
                        icon={selected?.value}
                        className="size-4"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    triggerRef.current?.focus();
                }}
                className="w-96 max-w-[calc(100vw-1rem)] overflow-hidden p-0"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        value={search}
                        onValueChange={setSearch}
                        aria-label={t('Search icons')}
                        placeholder={t('Search icons...')}
                    />
                    <CommandList
                        role="region"
                        aria-label={t('Amenity icons')}
                        className="max-h-[min(55vh,24rem)] overscroll-contain"
                    >
                        {filteredOptions.length === 0 ? (
                            <div
                                role="status"
                                className="px-4 py-8 text-center text-sm text-muted-foreground"
                            >
                                {t('No icons found.')}
                            </div>
                        ) : normalizedSearch ? (
                            <section className="space-y-2 p-2">
                                <h3 className="px-2 text-xs font-medium text-muted-foreground">
                                    {t('Results')}
                                </h3>
                                <IconGrid
                                    label={t('Icon search results')}
                                    options={filteredOptions}
                                    selected={selected?.value ?? null}
                                    onSelect={selectIcon}
                                />
                            </section>
                        ) : (
                            <div className="space-y-4 p-2">
                                <section className="space-y-2">
                                    <h3 className="px-2 text-xs font-medium text-muted-foreground">
                                        {t('Common')}
                                    </h3>
                                    <IconGrid
                                        label={t('Common amenity icons')}
                                        options={commonOptions}
                                        selected={selected?.value ?? null}
                                        onSelect={selectIcon}
                                    />
                                </section>
                                <section className="space-y-2">
                                    <h3 className="px-2 text-xs font-medium text-muted-foreground">
                                        {t('All icons')}
                                    </h3>
                                    <IconGrid
                                        label={t('All amenity icons')}
                                        options={amenityIconOptions}
                                        selected={selected?.value ?? null}
                                        onSelect={selectIcon}
                                    />
                                </section>
                            </div>
                        )}
                    </CommandList>
                    {selected && (
                        <div className="border-t p-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full justify-start text-muted-foreground"
                                onClick={() => selectIcon(null)}
                            >
                                <X className="size-4" />
                                {t('Clear selection')}
                            </Button>
                        </div>
                    )}
                </Command>
            </PopoverContent>
        </Popover>
    );
}
