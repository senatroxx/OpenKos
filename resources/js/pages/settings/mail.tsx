import { Form, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edit as editMail, update as updateMail, test as testMail } from '@/routes/settings/mail';

export default function Mail({ settings }: { settings: { mail_driver: string; mail_host: string | null; mail_port: number | null; mail_username: string | null; mail_encryption: string | null; mail_from_address: string | null; mail_from_name: string | null } }) {
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
                                    Leave empty to use the default log driver (emails will not be sent).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_host">SMTP Host</Label>
                                    <Input
                                        id="mail_host"
                                        name="mail_host"
                                        type="text"
                                        defaultValue={settings.mail_host ?? ''}
                                        placeholder="smtp.example.com"
                                    />
                                    {errors.mail_host && <p className="text-sm text-red-600">{errors.mail_host}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_port">Port</Label>
                                    <Input
                                        id="mail_port"
                                        name="mail_port"
                                        type="number"
                                        defaultValue={settings.mail_port ?? ''}
                                        placeholder="587"
                                    />
                                    {errors.mail_port && <p className="text-sm text-red-600">{errors.mail_port}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_username">Username</Label>
                                    <Input
                                        id="mail_username"
                                        name="mail_username"
                                        type="text"
                                        defaultValue={settings.mail_username ?? ''}
                                        placeholder="user@example.com"
                                    />
                                    {errors.mail_username && <p className="text-sm text-red-600">{errors.mail_username}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_password">Password</Label>
                                    <Input
                                        id="mail_password"
                                        name="mail_password"
                                        type="password"
                                        placeholder="Enter SMTP password"
                                    />
                                    {errors.mail_password && <p className="text-sm text-red-600">{errors.mail_password}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_encryption">Encryption</Label>
                                    <Select name="mail_encryption" defaultValue={settings.mail_encryption ?? 'null'}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="null">None</SelectItem>
                                            <SelectItem value="tls">TLS</SelectItem>
                                            <SelectItem value="ssl">SSL</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.mail_encryption && <p className="text-sm text-red-600">{errors.mail_encryption}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_from_address">From Address</Label>
                                    <Input
                                        id="mail_from_address"
                                        name="mail_from_address"
                                        type="email"
                                        defaultValue={settings.mail_from_address ?? ''}
                                        placeholder="noreply@openkos.app"
                                    />
                                    {errors.mail_from_address && <p className="text-sm text-red-600">{errors.mail_from_address}</p>}
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_from_name">From Name</Label>
                                    <Input
                                        id="mail_from_name"
                                        name="mail_from_name"
                                        type="text"
                                        defaultValue={settings.mail_from_name ?? ''}
                                        placeholder="OpenKOS"
                                    />
                                    {errors.mail_from_name && <p className="text-sm text-red-600">{errors.mail_from_name}</p>}
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
                        A test email will be sent to the configured <strong>From Address</strong>.
                    </p>
                </CardContent>
                <CardFooter>
                    <Button variant="secondary" asChild>
                        <Link href={testMail.url()} method="post" as="button">Send Test Email</Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}

Mail.layout = {
    breadcrumbs: [
        { title: 'Mail settings', href: editMail() },
    ],
};
