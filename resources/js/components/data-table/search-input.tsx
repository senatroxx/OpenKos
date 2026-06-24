import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    onClear: () => void;
    placeholder?: string;
};

export function SearchInput({
    value,
    onChange,
    onClear,
    placeholder = 'Search...',
}: SearchInputProps) {
    return (
        <div className="relative flex-1 md:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                placeholder={placeholder}
                className="pl-9"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {value && (
                <button
                    type="button"
                    aria-label="Clear search"
                    onClick={onClear}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X className="size-3.5" />
                </button>
            )}
        </div>
    );
}
