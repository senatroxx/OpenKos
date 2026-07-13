import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/shared';
import { Input } from '@/components/ui/input';
import { InstallStepper } from '@/components/install/stepper';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { createAdmin } from '@/routes/install';

type Props = {
    steps: Record<string, boolean>;
};

export default function InstallAdmin({ steps }: Props) {
    return (
        <>
            <Head title="Administrator Setup" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Administrator Setup</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Create the first administrator account
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...createAdmin.form()}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Full Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="John Doe"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email Address</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            placeholder="admin@example.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            required
                                            placeholder="Min. 8 characters"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm Password
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            required
                                        />
                                        <InputError
                                            message={errors.password_confirmation}
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Create Administrator
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
