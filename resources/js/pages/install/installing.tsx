import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { InstallStepper } from '@/components/install/stepper';
import { Spinner } from '@/components/ui/spinner';
import { runInstall } from '@/routes/install';

type Props = {
    steps: Record<string, boolean>;
};

export default function InstallInstalling({ steps }: Props) {
    const [status, setStatus] = useState<'idle' | 'running' | 'error' | 'done'>('idle');
    const [message, setMessage] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (status !== 'idle') return;

        setStatus('running');
        setMessage('Generating application key...');

        router.post(
            runInstall.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setStatus('done');
                    setMessage('Installation complete!');
                },
                onError: (errors) => {
                    setStatus('error');
                    setError(errors.install ?? 'An error occurred during installation.');
                },
                onFinish: () => {},
            },
        );
    }, [status]);

    const retry = () => {
        setStatus('idle');
        setError(null);
    };

    return (
        <>
            <Head title="Installing" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Installing OpenKOS</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Setting up your platform
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <div className="flex flex-col items-center gap-4 text-center">
                            {status === 'idle' || status === 'running' ? (
                                <>
                                    <Spinner className="h-8 w-8" />
                                    <p className="text-sm text-muted-foreground">
                                        {message || 'Running installation...'}
                                    </p>
                                </>
                            ) : status === 'error' ? (
                                <>
                                    <span className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-xl text-red-600 dark:bg-red-900 dark:text-red-400">
                                        ✗
                                    </span>
                                    <p className="text-sm text-red-600 dark:text-red-400">
                                        {error}
                                    </p>
                                    <Button onClick={retry} variant="outline">
                                        Retry
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <span className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-xl text-green-600 dark:bg-green-900 dark:text-green-400">
                                        ✓
                                    </span>
                                    <p className="text-sm text-green-600 dark:text-green-400">
                                        Installation completed successfully!
                                    </p>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
