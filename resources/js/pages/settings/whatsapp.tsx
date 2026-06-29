import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edit as editWhatsApp, update as updateWhatsApp, test as testWhatsApp } from '@/routes/settings/whatsapp';

type DriverSchemaField = {
    label: string;
    type: string;
    required: boolean;
    placeholder?: string;
};

type Driver = {
    name: string;
    label: string;
    configuration_schema: Record<string, DriverSchemaField>;
    supports_pairing: boolean;
};

export default function WhatsApp({
    drivers,
    settings,
}: {
    drivers: Driver[];
    settings: { whatsapp_driver: string | null; whatsapp_config: Record<string, Record<string, string>> | null };
}) {
    const [driver, setDriver] = useState(settings.whatsapp_driver ?? 'log');

    const currentDriver = drivers.find((d) => d.name === driver);
    const fields = currentDriver?.configuration_schema ?? {};
    const driverConfig = settings.whatsapp_config?.[driver] ?? {};

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">WhatsApp settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure the active WhatsApp driver and its credentials.
                </p>
            </div>

            <Form action={updateWhatsApp()}>
                {({ processing, errors }) => (
                    <Card>
                        <CardHeader>
                            <CardTitle>WhatsApp Driver</CardTitle>
                            <CardDescription>
                                Select the active WhatsApp driver and configure its credentials.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="whatsapp_driver">Driver</Label>
                                <input type="hidden" name="whatsapp_driver" value={driver} />
                                <Select
                                    value={driver}
                                    onValueChange={(value) => setDriver(value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {drivers.map((d) => (
                                            <SelectItem key={d.name} value={d.name}>
                                                {d.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.whatsapp_driver && (
                                    <p className="text-sm text-red-600">{errors.whatsapp_driver}</p>
                                )}
                            </div>

                            {Object.keys(fields).length > 0 && Object.entries(fields).map(([key, field]) => (
                                <div key={key} className="grid max-w-xs gap-2">
                                    <Label htmlFor={`config-${key}`}>
                                        {field.label}
                                        {field.required && <span className="text-destructive"> *</span>}
                                    </Label>
                                    <Input
                                        id={`config-${key}`}
                                        name={`whatsapp_config[${driver}][${key}]`}
                                        type={field.type === 'password' ? 'password' : field.type === 'url' ? 'url' : 'text'}
                                        defaultValue={driverConfig[key] ?? ''}
                                        placeholder={field.placeholder ?? ''}
                                    />
                                    {errors[`whatsapp_config.${driver}.${key}`] && (
                                        <p className="text-sm text-red-600">
                                            {errors[`whatsapp_config.${driver}.${key}`]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                        <CardFooter>
                            <Button disabled={processing}>Save</Button>
                        </CardFooter>
                    </Card>
                )}
            </Form>


            <Card>
                <CardHeader>
                    <CardTitle>Test Connection</CardTitle>
                    <CardDescription>
                        Verify that the active WhatsApp driver is working correctly.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        A health check will be performed on the active driver.
                    </p>
                </CardContent>
                <CardFooter>
                    <Button variant="secondary" asChild>
                        <Link href={testWhatsApp.url()} method="post" as="button">
                            Test Connection
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}

WhatsApp.layout = {
    breadcrumbs: [
        { title: 'WhatsApp settings', href: editWhatsApp() },
    ],
};
