import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edit as editWhatsApp, update as updateWhatsApp, test as testWhatsApp, pair as pairWhatsApp } from '@/routes/settings/whatsapp';

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
    const selectedDriver = settings.whatsapp_driver ?? 'log';
    const [driver, setDriver] = useState(selectedDriver);
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [pairingError, setPairingError] = useState<string | null>(null);
    const [pairingLoading, setPairingLoading] = useState(false);

    const currentDriver = drivers.find((d) => d.name === driver);
    const fields = currentDriver?.configuration_schema ?? {};
    const driverConfig = settings.whatsapp_config?.[driver] ?? {};

    const handlePair = async () => {
        setPairingLoading(true);
        setPairingError(null);
        setQrCode(null);

        try {
            const res = await fetch(pairWhatsApp.url(), {
                method: 'post',
                headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '', Accept: 'application/json' },
            });

            const data = await res.json();

            if (data.qr_code) {
                setQrCode(data.qr_code);
            } else if (data.message) {
                setPairingError(data.message);
            } else {
                setPairingError(data.error ?? 'Failed to get QR code.');
            }
        } catch {
            setPairingError('Could not connect to server.');
        } finally {
            setPairingLoading(false);
        }
    };

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
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Driver</CardTitle>
                                <CardDescription>
                                    Select the active WhatsApp driver. Only one driver can be active at a time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="whatsapp_driver">Driver</Label>
                                    <input type="hidden" name="whatsapp_driver" value={driver} />
                                    <Select
                                        value={driver}
                                        onValueChange={(value) => setDriver(value)}
                                        defaultValue={selectedDriver}
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
                            </CardContent>
                            <CardFooter>
                                <Button disabled={processing}>Save</Button>
                            </CardFooter>
                        </Card>

                        {Object.keys(fields).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{currentDriver!.label} Configuration</CardTitle>
                                    <CardDescription>
                                        Enter the credentials for the {currentDriver!.label} driver.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {Object.entries(fields).map(([key, field]) => (
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
                    </div>
                )}
            </Form>

            {currentDriver?.supports_pairing && (
                <Card>
                    <CardHeader>
                        <CardTitle>Pair Device</CardTitle>
                        <CardDescription>
                            Scan the QR code below with your phone to connect your WhatsApp device.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {pairingError && (
                            <p className="text-sm text-muted-foreground">{pairingError}</p>
                        )}
                        {qrCode && (
                            <img
                                src={`data:image/png;base64,${qrCode}`}
                                alt="QR Code"
                                className="mx-auto h-64 w-64"
                            />
                        )}
                    </CardContent>
                    <CardFooter>
                        <Button onClick={handlePair} disabled={pairingLoading}>
                            {pairingLoading ? 'Loading...' : 'Get QR Code'}
                        </Button>
                    </CardFooter>
                </Card>
            )}

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
