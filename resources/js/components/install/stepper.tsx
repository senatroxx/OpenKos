import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

const STEPS = [
    { key: 'welcome', label: 'Welcome' },
    { key: 'requirements', label: 'Requirements' },
    { key: 'database', label: 'Database' },
    { key: 'admin', label: 'Administrator' },
    { key: 'organization', label: 'Organization' },
    { key: 'installing', label: 'Installing' },
    { key: 'completed', label: 'Complete' },
] as const;

type Props = {
    steps: Record<string, boolean | null>;
};

export function InstallStepper({ steps }: Props) {
    return (
        <nav aria-label="Installation progress" className="-mx-12 w-[calc(100%+6rem)]">
            <ol className="flex w-full items-start">
                {STEPS.map((step, index) => {
                    const s = steps[step.key];
                    const completed = s === true;
                    const isActive = s === false;
                    const future = s === null;
                    const isLast = index === STEPS.length - 1;

                    return (
                        <li
                            key={step.key}
                            className="flex flex-1 flex-col items-center"
                        >
                            <div className="flex w-full items-center">
                                <div
                                    className={cn(
                                        'h-px flex-1',
                                        index === 0 && 'invisible',
                                        completed
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                                <span
                                    className={cn(
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                                        completed
                                            ? 'bg-primary text-primary-foreground'
                                            : isActive
                                                ? 'border-2 border-primary bg-card text-primary'
                                                : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {completed ? (
                                        <Check className="h-3.5 w-3.5" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <div
                                    className={cn(
                                        'h-px flex-1',
                                        isLast && 'invisible',
                                        completed
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            </div>
                            <span
                                className={cn(
                                    'mt-1.5 text-center text-xs',
                                    isActive
                                        ? 'font-medium text-primary'
                                        : completed
                                            ? 'text-muted-foreground'
                                            : 'text-muted-foreground/50',
                                )}
                            >
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
                })}
            </ol>
        </nav>
    );
}
