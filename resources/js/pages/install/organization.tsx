import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/shared';
import { Input } from '@/components/ui/input';
import { InstallStepper } from '@/components/install/stepper';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { setupOrganization } from '@/routes/install';

type Props = {
    steps: Record<string, boolean>;
    pluginSteps: Array<{ key: string; label: string; route: string }>;
    whatsappDrivers: Array<{ name: string; label: string }>;
};

export default function InstallOrganization({ pluginSteps, steps, whatsappDrivers }: Props) {
    return (
        <>
            <Head title="Organization Setup" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Organization Setup</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Configure your property and notification channels
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

                                    <hr className="border-border" />

                                    <div>
                                        <h2 className="text-lg font-medium">Email (SMTP)</h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Optional. Leave empty to use log driver (emails will
                                            not be sent).
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="mail_host">SMTP Host</Label>
                                        <Input
                                            id="mail_host"
                                            name="mail_host"
                                            placeholder="smtp.example.com"
                                        />
                                        <InputError message={errors.mail_host} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_port">Port</Label>
                                            <Input
                                                id="mail_port"
                                                name="mail_port"
                                                placeholder="587"
                                            />
                                            <InputError message={errors.mail_port} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_encryption">
                                                Encryption
                                            </Label>
                                            <Select name="mail_encryption">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="None" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="null">None</SelectItem>
                                                    <SelectItem value="tls">TLS</SelectItem>
                                                    <SelectItem value="ssl">SSL</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.mail_encryption} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_username">Username</Label>
                                            <Input
                                                id="mail_username"
                                                name="mail_username"
                                                placeholder="user@example.com"
                                            />
                                            <InputError message={errors.mail_username} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_password">Password</Label>
                                            <Input
                                                id="mail_password"
                                                name="mail_password"
                                                type="password"
                                            />
                                            <InputError message={errors.mail_password} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_from_address">
                                                From Address
                                            </Label>
                                            <Input
                                                id="mail_from_address"
                                                name="mail_from_address"
                                                type="email"
                                                placeholder="noreply@openkos.app"
                                            />
                                            <InputError
                                                message={errors.mail_from_address}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="mail_from_name">
                                                From Name
                                            </Label>
                                            <Input
                                                id="mail_from_name"
                                                name="mail_from_name"
                                                placeholder="OpenKOS"
                                            />
                                            <InputError message={errors.mail_from_name} />
                                        </div>
                                    </div>

                                    <hr className="border-border" />

                                    <div className="grid gap-2">
                                        <Label htmlFor="whatsapp_driver">
                                            WhatsApp Driver
                                        </Label>
                                        <Select name="whatsapp_driver">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Log (no real messages)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {whatsappDrivers.map((d) => (
                                                    <SelectItem key={d.name} value={d.name}>
                                                        {d.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            Configure credentials in settings after setup.
                                        </p>
                                        <InputError message={errors.whatsapp_driver} />
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
