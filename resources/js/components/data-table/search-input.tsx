import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    onClear: () => void;
    placeholder?: string;
    className?: string;
    id?: string;
    'aria-label'?: string;
};

export function SearchInput({
    value,
    onChange,
    onClear,
    placeholder = 'Search...',
    className,
    id,
    'aria-label': ariaLabel,
}: SearchInputProps) {
    return (
        <div className={cn('relative flex-1 md:max-w-xs', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id={id}
                aria-label={ariaLabel}
                placeholder={t(placeholder)}
                className="bg-card pl-9"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {value && (
                <button
                    type="button"
                    aria-label={t('Clear search')}
                    onClick={onClear}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X className="size-3.5" />
                </button>
            )}
        </div>
    );
}
