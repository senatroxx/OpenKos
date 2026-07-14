import { Head, usePage } from '@inertiajs/react';
import { InstallStepper } from '@/components/install/stepper';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

type Props = {
    steps: Record<string, boolean>;
};

export default function InstallFinished({ steps }: Props) {
    const { setting } = usePage<{ setting: { site_name: string } }>().props;

    return (
        <>
            <Head title="Installation Complete" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-3xl font-bold">Installation Complete</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            OpenKOS has been installed successfully
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <div className="flex flex-col items-center gap-6 text-center">
                            <span className="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-600 dark:bg-green-900 dark:text-green-400">
                                ✓
                            </span>

                            <div>
                                <h2 className="text-xl font-medium">
                                    Welcome to {setting?.site_name || 'OpenKOS'}
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Your platform is ready. You can now log in with the
                                    administrator account you just created.
                                </p>
                            </div>

                            <a href={login().url}>
                                <Button className="w-full">Go to Login</Button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
