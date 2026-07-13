import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/shared';
import { Input } from '@/components/ui/input';
import { InstallStepper } from '@/components/install/stepper';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { setupOrganization } from '@/routes/install';

type Props = {
    steps: Record<string, boolean>;
    pluginSteps: Array<{ key: string; label: string; route: string }>;
};

export default function InstallOrganization({ pluginSteps, steps }: Props) {
    return (
        <>
            <Head title="Organization Setup" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Organization Setup</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Configure your property organization
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...setupOrganization.form()}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="site_name">
                                            Organization / Property Name
                                        </Label>
                                        <Input
                                            id="site_name"
                                            name="site_name"
                                            required
                                            autoFocus
                                            placeholder="My Boarding House"
                                        />
                                        <InputError message={errors.site_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="country_code">
                                            Country Code
                                        </Label>
                                        <Input
                                            id="country_code"
                                            name="country_code"
                                            required
                                            maxLength={2}
                                            defaultValue="ID"
                                            placeholder="ID"
                                        />
                                        <InputError message={errors.country_code} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="timezone">Timezone</Label>
                                        <Input
                                            id="timezone"
                                            name="timezone"
                                            required
                                            defaultValue="Asia/Jakarta"
                                            placeholder="Asia/Jakarta"
                                        />
                                        <InputError message={errors.timezone} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">Currency</Label>
                                        <Input
                                            id="currency"
                                            name="currency"
                                            required
                                            maxLength={3}
                                            defaultValue="IDR"
                                            placeholder="IDR"
                                        />
                                        <InputError message={errors.currency} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="locale">Locale</Label>
                                        <Input
                                            id="locale"
                                            name="locale"
                                            required
                                            maxLength={2}
                                            defaultValue="id"
                                            placeholder="id"
                                        />
                                        <InputError message={errors.locale} />
                                    </div>

                                    {pluginSteps.length > 0 && (
                                        <div className="rounded-md bg-muted p-4">
                                            <p className="text-sm font-medium">
                                                Additional Configuration
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Plugins have registered additional setup
                                                steps.
                                            </p>
                                        </div>
                                    )}

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Complete Setup
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
