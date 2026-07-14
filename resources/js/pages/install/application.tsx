import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/shared';
import { Input } from '@/components/ui/input';
import { InstallStepper } from '@/components/install/stepper';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { configureApplication } from '@/routes/install';

type Props = {
    steps: Record<string, boolean | null>;
};

export default function InstallApplication({ steps }: Props) {
    return (
        <>
            <Head title="Application Settings" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Application Settings</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Configure your application
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...configureApplication.form()}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="app_url">Application URL</Label>
                                        <Input
                                            id="app_url"
                                            name="app_url"
                                            type="url"
                                            required
                                            defaultValue="http://localhost:8000"
                                            placeholder="http://localhost:8000"
                                        />
                                        <InputError message={errors.app_url} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="app_name">Application Name</Label>
                                        <Input
                                            id="app_name"
                                            name="app_name"
                                            required
                                            defaultValue="OpenKOS"
                                            placeholder="OpenKOS"
                                        />
                                        <InputError message={errors.app_name} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="timezone">Timezone</Label>
                                            <Input
                                                id="timezone"
                                                name="timezone"
                                                required
                                                defaultValue="Asia/Jakarta"
                                            />
                                            <InputError message={errors.timezone} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="locale">Locale</Label>
                                            <Input
                                                id="locale"
                                                name="locale"
                                                required
                                                disabled
                                                defaultValue="en"
                                            />
                                            <InputError message={errors.locale} />
                                        </div>
                                    </div>
                                    <p className="-mt-2 text-xs text-muted-foreground">
                                        Internationalization is not yet implemented. Locale defaults to English.
                                    </p>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Continue
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}
