import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

const STEPS = [
    { key: 'welcome', label: 'Welcome' },
    { key: 'requirements', label: 'Requirements' },
    { key: 'database', label: 'Database' },
    { key: 'application', label: 'Application' },
    { key: 'admin', label: 'Administrator' },
    { key: 'organization', label: 'Organization' },
    { key: 'notifications', label: 'Notifications' },
    { key: 'installing', label: 'Installing' },
    { key: 'completed', label: 'Complete' },
] as const;

type Props = {
    steps: Record<string, boolean | null>;
};

export function InstallStepper({ steps }: Props) {
    const currentIndex = STEPS.findIndex(s => steps[s.key] === false);
    const currentStep = currentIndex !== -1 ? STEPS[currentIndex] : null;

    return (
        <>
            <nav aria-label="Installation progress" className="hidden md:-mx-12 md:w-[calc(100%+6rem)] md:block">
                <ol className="flex w-full items-center">
                    {STEPS.map((step, index) => {
                        const s = steps[step.key];
                        const completed = s === true;
                        const isActive = s === false;
                        const isLast = index === STEPS.length - 1;
                        const prevCompleted = index > 0 && steps[STEPS[index - 1]?.key] === true;

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
                                            prevCompleted
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
            <p className="text-center text-sm text-muted-foreground md:hidden">
                Step {currentStep ? currentIndex + 1 : STEPS.length} of {STEPS.length}
                {currentStep && <> &mdash; {currentStep.label}</>}
            </p>
        </>
    );
}
