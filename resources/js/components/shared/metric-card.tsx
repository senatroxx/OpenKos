import React from 'react';
import { cn } from '@/lib/utils';

export type MetricVariant =
    | 'neutral'
    | 'blue'
    | 'green'
    | 'amber'
    | 'red'
    | 'purple';

export type MetricEmphasis = 'neutral' | 'subtle' | 'attention';

export interface MetricCardProps {
    label: string;
    value: React.ReactNode;
    subtext?: React.ReactNode;
    variant?: MetricVariant;
    emphasis?: MetricEmphasis;
    icon?: React.ComponentType<{ className?: string }>;
    progress?: number;
    onClick?: () => void;
    className?: string;
}

const VARIANT_STYLES: Record<
    MetricVariant,
    Record<
        MetricEmphasis,
        {
            container: string;
            value: string;
            iconContainer: string;
            iconText: string;
            progressBar: string;
            progressTrack: string;
        }
    >
> = {
    neutral: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-foreground font-bold',
            iconContainer: 'bg-muted text-muted-foreground',
            iconText: 'text-muted-foreground',
            progressBar: 'bg-primary',
            progressTrack: 'bg-muted',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-foreground font-bold',
            iconContainer: 'bg-muted text-muted-foreground',
            iconText: 'text-muted-foreground',
            progressBar: 'bg-primary',
            progressTrack: 'bg-muted',
        },
        attention: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-foreground font-bold',
            iconContainer: 'bg-muted text-muted-foreground',
            iconText: 'text-muted-foreground',
            progressBar: 'bg-primary',
            progressTrack: 'bg-muted',
        },
    },
    blue: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-blue-foreground font-bold',
            iconContainer: 'bg-surface-blue-icon text-surface-blue-foreground',
            iconText: 'text-surface-blue-foreground',
            progressBar: 'bg-surface-blue-foreground',
            progressTrack: 'bg-surface-blue-icon/40',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-blue-foreground font-bold',
            iconContainer: 'bg-surface-blue-icon text-surface-blue-foreground',
            iconText: 'text-surface-blue-foreground',
            progressBar: 'bg-surface-blue-foreground',
            progressTrack: 'bg-surface-blue-icon/40',
        },
        attention: {
            container:
                'bg-card text-card-foreground border-border border-t-2 border-t-surface-blue-foreground shadow-xs',
            value: 'text-surface-blue-foreground font-bold',
            iconContainer: 'bg-surface-blue-icon text-surface-blue-foreground',
            iconText: 'text-surface-blue-foreground',
            progressBar: 'bg-surface-blue-foreground',
            progressTrack: 'bg-surface-blue-icon/40',
        },
    },
    green: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-green-foreground font-bold',
            iconContainer:
                'bg-surface-green-icon text-surface-green-foreground',
            iconText: 'text-surface-green-foreground',
            progressBar: 'bg-surface-green-foreground',
            progressTrack: 'bg-surface-green-icon/40',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-green-foreground font-bold',
            iconContainer:
                'bg-surface-green-icon text-surface-green-foreground',
            iconText: 'text-surface-green-foreground',
            progressBar: 'bg-surface-green-foreground',
            progressTrack: 'bg-surface-green-icon/40',
        },
        attention: {
            container:
                'bg-card text-card-foreground border-border border-t-2 border-t-surface-green-foreground shadow-xs',
            value: 'text-surface-green-foreground font-bold',
            iconContainer:
                'bg-surface-green-icon text-surface-green-foreground',
            iconText: 'text-surface-green-foreground',
            progressBar: 'bg-surface-green-foreground',
            progressTrack: 'bg-surface-green-icon/40',
        },
    },
    amber: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-amber-foreground font-bold',
            iconContainer:
                'bg-surface-amber-icon text-surface-amber-foreground',
            iconText: 'text-surface-amber-foreground',
            progressBar: 'bg-surface-amber-foreground',
            progressTrack: 'bg-surface-amber-icon/40',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-amber-foreground font-bold',
            iconContainer:
                'bg-surface-amber-icon text-surface-amber-foreground',
            iconText: 'text-surface-amber-foreground',
            progressBar: 'bg-surface-amber-foreground',
            progressTrack: 'bg-surface-amber-icon/40',
        },
        attention: {
            container:
                'bg-card text-card-foreground border-border border-t-2 border-t-surface-amber-foreground shadow-xs',
            value: 'text-surface-amber-foreground font-bold',
            iconContainer:
                'bg-surface-amber-icon text-surface-amber-foreground',
            iconText: 'text-surface-amber-foreground',
            progressBar: 'bg-surface-amber-foreground',
            progressTrack: 'bg-surface-amber-icon/40',
        },
    },
    red: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-red-foreground font-bold',
            iconContainer: 'bg-surface-red-icon text-surface-red-foreground',
            iconText: 'text-surface-red-foreground',
            progressBar: 'bg-surface-red-foreground',
            progressTrack: 'bg-surface-red-icon/40',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-red-foreground font-bold',
            iconContainer: 'bg-surface-red-icon text-surface-red-foreground',
            iconText: 'text-surface-red-foreground',
            progressBar: 'bg-surface-red-foreground',
            progressTrack: 'bg-surface-red-icon/40',
        },
        attention: {
            container:
                'bg-card text-card-foreground border-border border-t-2 border-t-surface-red-foreground shadow-xs',
            value: 'text-surface-red-foreground font-bold',
            iconContainer: 'bg-surface-red-icon text-surface-red-foreground',
            iconText: 'text-surface-red-foreground',
            progressBar: 'bg-surface-red-foreground',
            progressTrack: 'bg-surface-red-icon/40',
        },
    },
    purple: {
        neutral: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-purple-foreground font-bold',
            iconContainer:
                'bg-surface-purple-icon text-surface-purple-foreground',
            iconText: 'text-surface-purple-foreground',
            progressBar: 'bg-surface-purple-foreground',
            progressTrack: 'bg-surface-purple-icon/40',
        },
        subtle: {
            container: 'bg-card text-card-foreground border-border shadow-2xs',
            value: 'text-surface-purple-foreground font-bold',
            iconContainer:
                'bg-surface-purple-icon text-surface-purple-foreground',
            iconText: 'text-surface-purple-foreground',
            progressBar: 'bg-surface-purple-foreground',
            progressTrack: 'bg-surface-purple-icon/40',
        },
        attention: {
            container:
                'bg-card text-card-foreground border-border border-t-2 border-t-surface-purple-foreground shadow-xs',
            value: 'text-surface-purple-foreground font-bold',
            iconContainer:
                'bg-surface-purple-icon text-surface-purple-foreground',
            iconText: 'text-surface-purple-foreground',
            progressBar: 'bg-surface-purple-foreground',
            progressTrack: 'bg-surface-purple-icon/40',
        },
    },
};

export function MetricCard({
    label,
    value,
    subtext,
    variant = 'neutral',
    emphasis = 'subtle',
    icon: Icon,
    progress,
    onClick,
    className,
}: MetricCardProps) {
    const activeEmphasis: MetricEmphasis =
        variant === 'neutral' ? 'neutral' : emphasis;
    const styles = VARIANT_STYLES[variant][activeEmphasis];
    const isInteractive = Boolean(onClick);

    return (
        <div
            onClick={onClick}
            role={isInteractive ? 'button' : undefined}
            tabIndex={isInteractive ? 0 : undefined}
            className={cn(
                'relative flex flex-col justify-between rounded-xl border p-4 transition-all duration-150',
                styles.container,
                isInteractive &&
                    'cursor-pointer hover:border-primary/50 hover:shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p
                        className={cn(
                            'mt-1.5 text-2xl font-bold tabular-nums sm:text-3xl',
                            styles.value,
                        )}
                    >
                        {value}
                    </p>
                    {subtext && (
                        <div className="mt-1 text-xs font-medium text-muted-foreground">
                            {subtext}
                        </div>
                    )}
                </div>

                {Icon && (
                    <div
                        className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                            styles.iconContainer,
                        )}
                    >
                        <Icon className={cn('size-4', styles.iconText)} />
                    </div>
                )}
            </div>

            {progress !== undefined && (
                <div className="mt-3">
                    <div className="mb-1 flex items-center justify-between text-xs font-medium text-muted-foreground">
                        <span>Progress</span>
                        <span className="tabular-nums">{progress}%</span>
                    </div>
                    <div
                        className={cn(
                            'h-1.5 w-full overflow-hidden rounded-full',
                            styles.progressTrack,
                        )}
                    >
                        <div
                            className={cn(
                                'h-full rounded-full transition-all duration-300',
                                styles.progressBar,
                            )}
                            style={{
                                width: `${Math.min(100, Math.max(0, progress))}%`,
                            }}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
