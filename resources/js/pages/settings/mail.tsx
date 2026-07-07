import { Form, Link } from '@inertiajs/react';
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

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">Mail settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure SMTP settings for sending emails.
                </p>
            </div>

            <Form action={updateMail()}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>SMTP Configuration</CardTitle>
                                <CardDescription>
                                    Leave empty to use the default log driver
                                    (emails will not be sent).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_config[host]">
                                        SMTP Host
                                    </Label>
                                    <Input
                                        id="mail_config[host]"
                                        name="mail_config[host]"
                                        type="text"
                                        defaultValue={config.host ?? ''}
                                        placeholder="smtp.example.com"
                                    />
                                    {errors['mail_config.host'] && (
                                        <p className="text-sm text-red-600">
                                            {errors['mail_config.host']}
                                        </p>
                                    )}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_config[port]">
                                        Port
                                    </Label>
                                    <Input
                                        id="mail_config[port]"
                                        name="mail_config[port]"
                                        type="number"
                                        defaultValue={config.port ?? ''}
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
                                        defaultValue={config.username ?? ''}
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
                                        name="mail_config[encryption]"
                                        defaultValue={
                                            config.encryption ?? 'null'
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="null">
                                                None
                                            </SelectItem>
                                            <SelectItem value="tls">
                                                TLS
                                            </SelectItem>
                                            <SelectItem value="ssl">
                                                SSL
                                            </SelectItem>
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
                                        defaultValue={
                                            config.from_address ?? ''
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
                                        defaultValue={
                                            config.from_name ?? ''
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
                    </div>
                )}
            </Form>

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
