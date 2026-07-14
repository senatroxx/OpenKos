import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { InstallStepper } from '@/components/install/stepper';
import { Button } from '@/components/ui/button';
import { checkRequirements } from '@/routes/install';

type Requirement = {
    label: string;
    satisfied: boolean;
    value?: string;
};

type Props = {
    requirements: Record<string, Requirement>;
    allMet: boolean;
    steps: Record<string, boolean>;
};

export default function InstallRequirements({ requirements, allMet, steps }: Props) {
    const [submitting, setSubmitting] = useState(false);

    const proceed = () => {
        setSubmitting(true);
        router.post(checkRequirements.url(), undefined, {
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <>
            <Head title="System Requirements" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">System Requirements</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Checking your server meets the minimum requirements
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <div className="space-y-4">
                            {Object.entries(requirements).map(([key, req]) => (
                                <div
                                    key={key}
                                    className="flex items-center justify-between rounded-lg border bg-background px-4 py-3"
                                >
                                    <div>
                                        <span className="text-sm font-medium">
                                            {req.label}
                                        </span>
                                        {req.value && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                ({req.value})
                                            </span>
                                        )}
                                    </div>
                                    <span
                                        className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${
                                            req.satisfied
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                                        }`}
                                    >
                                        {req.satisfied ? '✓' : '✗'}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <Button
                            onClick={proceed}
                            className="mt-6 w-full"
                            disabled={!allMet || submitting}
                        >
                            {allMet ? 'Continue' : 'Requirements Not Met'}
                        </Button>

                        {!allMet && (
                            <p className="mt-2 text-center text-xs text-muted-foreground">
                                Please resolve the missing requirements before proceeding.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
