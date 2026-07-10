import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { countryCodes, parseE164 } from './country-codes';

export default function PhoneInput({
    name,
    defaultValue,
    placeholder,
    onChange,
}: {
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
    onChange?: (value: string) => void;
}) {
    const parsed = parseE164(defaultValue);
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState(parsed);
    const [number, setNumber] = useState(parsed.number);
    const [search, setSearch] = useState('');
    const [openKey, setOpenKey] = useState(0);

    const full = number ? selected.dialCode + number : '';

    useEffect(() => {
        onChange?.(full);
    }, [full, onChange]);

    const filtered = search
        ? countryCodes.filter(
              (c) =>
                  c.name.toLowerCase().includes(search.toLowerCase()) ||
                  c.iso2.toLowerCase().includes(search.toLowerCase()) ||
                  c.dialCode.includes(search),
          )
        : countryCodes;

    const handleOpenChange = (newOpen: boolean) => {
        setOpen(newOpen);

        if (newOpen) {
            setOpenKey((k) => k + 1);
        }
    };

    // ponytail: global selector, ref-based if perf matters
    useEffect(() => {
        if (!open) {
            return;
        }

        const id = requestAnimationFrame(() => {
            const el = document.querySelector(
                `[cmdk-item][data-value="${selected.iso2 + selected.dialCode}"]`,
            );
            el?.scrollIntoView({ block: 'center' });
        });

        return () => cancelAnimationFrame(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [openKey]);

    return (
        <div className="flex gap-2">
            <Popover open={open} onOpenChange={handleOpenChange}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        role="combobox"
                        aria-expanded={open}
                        className={cn(
                            'flex h-10 w-28 shrink-0 items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                            'focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none',
                            'hover:bg-accent hover:text-accent-foreground',
                        )}
                    >
                        <span className="flex items-center gap-1">
                            <span className="font-medium">{selected.iso2}</span>
                            <span className="text-muted-foreground">
                                {selected.dialCode}
                            </span>
                        </span>
                        <ChevronDown className="ml-1 size-4 shrink-0 text-muted-foreground" />
                    </button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-[280px] p-0"
                    align="start"
                    side="bottom"
                >
                    <Command
                        key={openKey}
                        shouldFilter={false}
                        defaultValue={selected.iso2 + selected.dialCode}
                    >
                        <CommandInput
                            placeholder="Search country or code"
                            value={search}
                            onValueChange={setSearch}
                        />
                        <CommandList>
                            <CommandEmpty>No country found</CommandEmpty>
                            <CommandGroup>
                                {filtered.map((c) => (
                                    <CommandItem
                                        key={c.iso2 + c.dialCode}
                                        value={c.iso2 + c.dialCode}
                                        onSelect={() => {
                                            setSelected({
                                                ...c,
                                                number: selected.number,
                                            });
                                            setOpen(false);
                                            setSearch('');
                                        }}
                                        className="flex cursor-pointer items-center gap-3 px-3 py-2.5"
                                    >
                                        <span
                                            className={cn(
                                                'flex size-4 shrink-0 items-center justify-center rounded-full border text-xs',
                                                selected.iso2 === c.iso2 &&
                                                    selected.dialCode ===
                                                        c.dialCode
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-muted-foreground/30',
                                            )}
                                        >
                                            {selected.iso2 === c.iso2 &&
                                                selected.dialCode ===
                                                    c.dialCode && (
                                                    <span className="size-1.5 rounded-full bg-current" />
                                                )}
                                        </span>
                                        <div className="flex flex-col">
                                            <span className="flex items-center gap-2 text-sm font-medium">
                                                <span>{c.iso2}</span>
                                                <span className="font-normal text-muted-foreground">
                                                    {c.dialCode}
                                                </span>
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {c.name}
                                            </span>
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            <input type="hidden" name={name} value={full} />
            <Input
                type="tel"
                value={number}
                onChange={(e) =>
                    setNumber(e.target.value.replace(/[^0-9]/g, ''))
                }
                placeholder={placeholder}
                className="h-10 flex-1"
            />
        </div>
    );
}
