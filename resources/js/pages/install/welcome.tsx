import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InstallStepper } from '@/components/install/stepper';
import { start } from '@/routes/install';

type Props = {
    version: string;
    steps: Record<string, boolean>;
};

export default function InstallWelcome({ version, steps }: Props) {
    const handleStart = () => {
        router.post(start.url());
    };

    return (
        <>
            <Head title="Installation Wizard" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-3xl font-bold">OpenKOS</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Property Management Platform v{version}
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <h2 className="mb-4 text-xl font-medium">Welcome to OpenKOS</h2>
                        <p className="mb-6 text-sm text-muted-foreground">
                            This wizard will guide you through the initial setup of your
                            OpenKOS installation. Before you begin, please make sure you
                            have your database credentials ready.
                        </p>
                        <p className="mb-6 text-sm text-muted-foreground">
                            OpenKOS is open-source software licensed under the MIT License.
                        </p>

                        <Button onClick={handleStart} className="w-full">
                            Start Installation
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
