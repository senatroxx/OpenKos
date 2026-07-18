import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    edit as editMail,
    update as updateMail,
    test as testMail,
} from '@/routes/settings/mail';

interface MailConfig {
    driver?: string;
    host?: string;
    port?: number;
    username?: string;
    encryption?: string;
    from_address?: string;
    from_name?: string;
}

export default function Mail({
    settings,
}: {
    settings: { mail_config: MailConfig | null };
}) {
    const config = settings.mail_config ?? {};

    const { data, setData, submit, processing, errors } = useForm({
        mail_config: {
            host: config.host ?? '',
            port: config.port != null ? String(config.port) : '',
            username: config.username ?? '',
            password: '',
            encryption: config.encryption ?? 'null',
            from_address: config.from_address ?? '',
            from_name: config.from_name ?? '',
        },
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(updateMail());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">Mail settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure SMTP settings for sending emails.
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>SMTP Configuration</CardTitle>
                        <CardDescription>
                            Leave empty to use the default log driver (emails
                            will not be sent).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[host]">SMTP Host</Label>
                            <Input
                                id="mail_config[host]"
                                name="mail_config[host]"
                                type="text"
                                value={data.mail_config.host}
                                onChange={(e) =>
                                    setData('mail_config.host', e.target.value)
                                }
                                placeholder="smtp.example.com"
                            />
                            {errors['mail_config.host'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.host']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[port]">Port</Label>
                            <Input
                                id="mail_config[port]"
                                name="mail_config[port]"
                                type="number"
                                value={data.mail_config.port}
                                onChange={(e) =>
                                    setData('mail_config.port', e.target.value)
                                }
                                placeholder="587"
                            />
                            {errors['mail_config.port'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.port']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[username]">
                                Username
                            </Label>
                            <Input
                                id="mail_config[username]"
                                name="mail_config[username]"
                                type="text"
                                value={data.mail_config.username}
                                onChange={(e) =>
                                    setData(
                                        'mail_config.username',
                                        e.target.value,
                                    )
                                }
                                placeholder="user@example.com"
                            />
                            {errors['mail_config.username'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.username']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[password]">
                                Password
                            </Label>
                            <Input
                                id="mail_config[password]"
                                name="mail_config[password]"
                                type="password"
                                value={data.mail_config.password}
                                onChange={(e) =>
                                    setData(
                                        'mail_config.password',
                                        e.target.value,
                                    )
                                }
                                placeholder="Enter SMTP password"
                            />
                            {errors['mail_config.password'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.password']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[encryption]">
                                Encryption
                            </Label>
                            <Select
                                value={data.mail_config.encryption}
                                onValueChange={(value) =>
                                    setData('mail_config.encryption', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="null">None</SelectItem>
                                    <SelectItem value="tls">TLS</SelectItem>
                                    <SelectItem value="ssl">SSL</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors['mail_config.encryption'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.encryption']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[from_address]">
                                From Address
                            </Label>
                            <Input
                                id="mail_config[from_address]"
                                name="mail_config[from_address]"
                                type="email"
                                value={data.mail_config.from_address}
                                onChange={(e) =>
                                    setData(
                                        'mail_config.from_address',
                                        e.target.value,
                                    )
                                }
                                placeholder="noreply@openkos.app"
                            />
                            {errors['mail_config.from_address'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.from_address']}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="mail_config[from_name]">
                                From Name
                            </Label>
                            <Input
                                id="mail_config[from_name]"
                                name="mail_config[from_name]"
                                type="text"
                                value={data.mail_config.from_name}
                                onChange={(e) =>
                                    setData(
                                        'mail_config.from_name',
                                        e.target.value,
                                    )
                                }
                                placeholder="OpenKOS"
                            />
                            {errors['mail_config.from_name'] && (
                                <p className="text-sm text-red-600">
                                    {errors['mail_config.from_name']}
                                </p>
                            )}
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button disabled={processing}>Save</Button>
                    </CardFooter>
                </Card>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle>Test Email</CardTitle>
                    <CardDescription>
                        Send a test email to verify your SMTP configuration.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        A test email will be sent to the configured{' '}
                        <strong>From Address</strong>.
                    </p>
                </CardContent>
                <CardFooter>
                    <Button variant="secondary" asChild>
                        <Link href={testMail.url()} method="post" as="button">
                            Send Test Email
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}

Mail.layout = {
    breadcrumbs: [{ title: 'Mail settings', href: editMail() }],
};
