import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type SegmentedToggleProps = {
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    ariaLabel?: string;
    className?: string;
};

export function SegmentedToggle({
    value,
    onChange,
    options,
    ariaLabel,
    className,
}: SegmentedToggleProps) {
    const activeIndex = options.findIndex((o) => o.value === value);
    const segmentPct = 100 / options.length;
    const padRem = 0.25;

    return (
        <div
            aria-label={ariaLabel}
            role="group"
            className={cn(
                'relative flex h-10 w-full rounded-full bg-muted p-1',
                className,
            )}
        >
            <div
                className="absolute inset-y-1 rounded-full bg-primary shadow-sm transition-all duration-200 ease-in-out"
                style={{
                    width: `calc(${segmentPct}% - ${padRem * 2}rem)`,
                    left: `calc(${activeIndex * segmentPct}% + ${padRem}rem)`,
                }}
            />
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    aria-pressed={option.value === value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'relative z-10 flex flex-1 cursor-pointer items-center justify-center rounded-full text-sm font-medium transition-colors duration-200',
                        option.value === value
                            ? 'text-primary-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    {t(option.label)}
                </button>
            ))}
        </div>
    );
}
