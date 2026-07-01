import { Form, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { edit as editWhatsApp, update as updateWhatsApp, test as testWhatsApp, pair as pairWhatsApp, qr as qrWhatsApp, disconnect as disconnectWhatsApp } from '@/routes/settings/whatsapp';

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
    connection,
}: {
    drivers: Driver[];
    settings: { whatsapp_driver: string | null; whatsapp_config: Record<string, Record<string, string>> | null };
    connection: { state: string; phone: string | null; lastConnected: string | null } | null;
}) {
    const [driver, setDriver] = useState(settings.whatsapp_driver ?? 'log');
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [pairingLoading, setPairingLoading] = useState(false);
    const [pairingError, setPairingError] = useState<string | null>(null);
    const [connectionStatus, setConnectionStatus] = useState<'unknown' | 'disconnected' | 'connecting' | 'connected'>(
        (connection?.state as 'connected' | 'disconnected') ?? 'unknown',
    );
    const [devicePhone, setDevicePhone] = useState<string | null>(connection?.phone ?? null);
    const [deviceLastConnected, setDeviceLastConnected] = useState<string | null>(connection?.lastConnected ?? null);

    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const currentDriver = drivers.find((d) => d.name === driver);
    const fields = currentDriver?.configuration_schema ?? {};
    const driverConfig = settings.whatsapp_config?.[driver] ?? {};
    const isBaileys = driver === 'baileys';
    const showDisconnect = isBaileys && connectionStatus === 'connected';

    const stopPolling = () => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    };

    const fetchStatus = async () => {
        try {
            const response = await fetch(statusWhatsApp().url);
            const data = await response.json();
            setConnectionStatus(data.healthy ? 'connected' : 'disconnected');
            setDevicePhone(data.phone ?? null);
            setDeviceLastConnected(data.lastConnected ?? null);
        } catch {
            setConnectionStatus('disconnected');
        }
    };

    const pollQr = async () => {
        try {
            const response = await fetch(qrWhatsApp().url);
            const data = await response.json();

            setConnectionStatus(data.state);
            setQrCode(data.qr_code ?? null);
            setDevicePhone(data.phone ?? null);
            setDeviceLastConnected(data.lastConnected ?? null);

            if (data.state === 'connected') {
                stopPolling();
            }
        } catch {
            stopPolling();
        }
    };

    useEffect(() => {
        if (isBaileys) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            fetchStatus();
        } else {
            stopPolling();
            setQrCode(null);
            setConnectionStatus('unknown');
            setDevicePhone(null);
            setDeviceLastConnected(null);
        }

        return () => stopPolling();
    }, [driver, isBaileys]);

    const handlePair = async () => {
        setPairingLoading(true);
        setPairingError(null);
        setQrCode(null);

        try {
            await fetch(pairWhatsApp().url, { method: 'POST' });
        } catch {
            setPairingError('Failed to initiate pairing.');
        }

        setPairingLoading(false);
        pollingRef.current = setInterval(pollQr, 2000);
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

            {isBaileys && (
                <Card>
                    <CardHeader>
                        <CardTitle>Device</CardTitle>
                        <CardDescription>
                            Manage your WhatsApp device connection.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center gap-2">
                            {connectionStatus === 'connected' ? (
                                <>
                                    <Badge variant="default">Connected</Badge>
                                    {devicePhone && (
                                        <span className="text-sm text-muted-foreground">{devicePhone}</span>
                                    )}
                                    {deviceLastConnected && (
                                        <span className="text-xs text-muted-foreground">
                                            Last connected: {new Date(deviceLastConnected).toLocaleString()}
                                        </span>
                                    )}
                                </>
                            ) : connectionStatus === 'connecting' ? (
                                <Badge variant="secondary">Connecting</Badge>
                            ) : connectionStatus === 'disconnected' ? (
                                <Badge variant="destructive">Disconnected</Badge>
                            ) : null}
                        </div>

                        {pairingError && (
                            <p className="text-sm text-red-600">{pairingError}</p>
                        )}

                        {(connectionStatus === 'connecting' && !qrCode && !pairingLoading) && (
                            <p className="text-sm text-muted-foreground">
                                Generating QR code...
                            </p>
                        )}

                        {qrCode && (
                            <div className="space-y-4">
                                <Separator />
                                <div className="space-y-3">
                                    <div className="inline-block border p-2 rounded-lg">
                                        <img
                                            src={`data:image/png;base64,${qrCode}`}
                                            alt="QR Code"
                                            className="size-48"
                                        />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Scan this QR code with your WhatsApp app to connect your device.
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter className="gap-2">
                        {connectionStatus !== 'connected' && (
                            <Button onClick={handlePair} disabled={pairingLoading}>
                                {pairingLoading ? 'Pairing...' : 'Pair Device'}
                            </Button>
                        )}
                        {showDisconnect && (
                            <Button variant="destructive" asChild>
                                <Link href={disconnectWhatsApp().url} method="delete" as="button">
                                    Disconnect
                                </Link>
                            </Button>
                        )}
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
