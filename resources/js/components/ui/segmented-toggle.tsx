import { cn } from '@/lib/utils';

type SegmentedToggleProps = {
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    className?: string;
};

export function SegmentedToggle({ value, onChange, options, className }: SegmentedToggleProps) {
    const activeIndex = options.findIndex((o) => o.value === value);
    const segmentPct = 100 / options.length;
    const padRem = 0.25;

    return (
        <div
            className={cn(
                'relative flex h-10 w-full rounded-full bg-primary p-1',
                className,
            )}
        >
            <div
                className="absolute inset-y-1 rounded-full bg-background shadow-sm transition-all duration-200 ease-in-out"
                style={{
                    width: `calc(${segmentPct}% - ${padRem * 2}rem)`,
                    left: `calc(${activeIndex * segmentPct}% + ${padRem}rem)`,
                }}
            />
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'relative z-10 flex flex-1 items-center justify-center rounded-full text-sm font-medium transition-colors duration-200',
                        option.value === value
                            ? 'text-primary'
                            : 'text-primary-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
