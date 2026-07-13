import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/shared';
import { Input } from '@/components/ui/input';
import { InstallStepper } from '@/components/install/stepper';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { configureDatabase } from '@/routes/install';

type Props = {
    connection: string;
    steps: Record<string, boolean>;
};

export default function InstallDatabase({ connection, steps }: Props) {
    const { props } = usePage();
    const pageErrors = props.errors as Record<string, string>;
    const [driver, setDriver] = useState(connection);

    return (
        <>
            <Head title="Database Configuration" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Database Configuration</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Enter your database connection details
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...configureDatabase.form()}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label>Database Driver</Label>
                                        <input type="hidden" name="connection" value={driver} />
                                        <Select value={driver} onValueChange={setDriver}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pgsql">PostgreSQL</SelectItem>
                                                <SelectItem value="mysql">MySQL</SelectItem>
                                                <SelectItem value="sqlite">SQLite</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.connection} />
                                    </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="host">Host</Label>
                                                <Input
                                                    key={`host-${driver}`}
                                                    id="host"
                                                    name="host"
                                                    defaultValue="127.0.0.1"
                                                    placeholder="127.0.0.1"
                                                />
                                                <InputError message={errors.host} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="port">Port</Label>
                                                <Input
                                                    key={`port-${driver}`}
                                                    id="port"
                                                    name="port"
                                                    defaultValue={driver === 'mysql' ? '3306' : '5432'}
                                                    placeholder={driver === 'mysql' ? '3306' : '5432'}
                                                />
                                                <InputError message={errors.port} />
                                            </div>
                                        </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="database">Database Name</Label>
                                        <Input
                                            id="database"
                                            name="database"
                                            defaultValue="openkos"
                                            placeholder="openkos"
                                        />
                                        <InputError message={errors.database} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="username">Username</Label>
                                            <Input
                                                id="username"
                                                name="username"
                                                defaultValue="root"
                                                placeholder="root"
                                            />
                                            <InputError message={errors.username} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">Password</Label>
                                            <Input
                                                id="password"
                                                name="password"
                                                type="password"
                                            />
                                            <InputError message={errors.password} />
                                        </div>
                                    </div>

                                    {pageErrors.connection && (
                                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950 dark:text-red-400">
                                            {pageErrors.connection}
                                        </div>
                                    )}

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Test Connection &amp; Continue
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
