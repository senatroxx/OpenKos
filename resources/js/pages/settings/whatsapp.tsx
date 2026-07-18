import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { Separator } from '@/components/ui/separator';
import {
    edit as editWhatsApp,
    update as updateWhatsApp,
    test as testWhatsApp,
    pair as pairWhatsApp,
    qr as qrWhatsApp,
    disconnect as disconnectWhatsApp,
} from '@/routes/settings/whatsapp';
import type { Driver } from '@/types';

export default function WhatsApp({
    drivers,
    settings,
    connection,
}: {
    drivers: Driver[];
    settings: {
        whatsapp_driver: string | null;
        whatsapp_config: Record<string, Record<string, string>> | null;
    };
    connection: {
        state: string;
        phone: string | null;
        lastConnected: string | null;
    } | null;
}) {
    const { data, setData, submit, processing, errors } = useForm<{
        whatsapp_driver: string;
        whatsapp_config: Record<string, Record<string, string>>;
    }>({
        whatsapp_driver: settings.whatsapp_driver ?? 'log',
        whatsapp_config: settings.whatsapp_config ?? {},
    });
    const driver = data.whatsapp_driver;

    const [qrCode, setQrCode] = useState<string | null>(null);
    const [pairingLoading, setPairingLoading] = useState(false);
    const [pairingError, setPairingError] = useState<string | null>(null);
    const [connectionStatus, setConnectionStatus] = useState<
        'unknown' | 'disconnected' | 'connecting' | 'connected'
    >((connection?.state as 'connected' | 'disconnected') ?? 'unknown');
    const [devicePhone, setDevicePhone] = useState<string | null>(
        connection?.phone ?? null,
    );
    const [deviceLastConnected, setDeviceLastConnected] = useState<
        string | null
    >(connection?.lastConnected ?? null);

    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const currentDriver = drivers.find((d) => d.name === driver);
    const fields = currentDriver?.configuration_schema ?? {};
    const driverConfig = data.whatsapp_config[driver] ?? {};
    const isBaileys = driver === 'baileys';
    const showDisconnect = isBaileys && connectionStatus === 'connected';

    const stopPolling = () => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
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
        if (!isBaileys) {
            stopPolling();
            // eslint-disable-next-line react-hooks/set-state-in-effect
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

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(updateWhatsApp());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">WhatsApp settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure the active WhatsApp driver and its credentials.
                </p>
            </div>

            <form onSubmit={handleSubmit}>
                <Card>
                    <CardHeader>
                        <CardTitle>WhatsApp Driver</CardTitle>
                        <CardDescription>
                            Select the active WhatsApp driver and configure its
                            credentials.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="whatsapp_driver">Driver</Label>
                            <Select
                                value={driver}
                                onValueChange={(value) =>
                                    setData('whatsapp_driver', value)
                                }
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
                                <p className="text-sm text-red-600">
                                    {errors.whatsapp_driver}
                                </p>
                            )}
                        </div>

                        {Object.keys(fields).length > 0 && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Values set via environment variables override
                                the fields below and cannot be changed here.
                            </div>
                        )}

                        {Object.keys(fields).length > 0 &&
                            Object.entries(fields).map(([key, field]) => {
                                const fieldError =
                                    errors[
                                        `whatsapp_config.${driver}.${key}` as keyof typeof errors
                                    ];

                                return (
                                    <div
                                        key={key}
                                        className="grid max-w-xs gap-2"
                                    >
                                        <Label htmlFor={`config-${key}`}>
                                            {field.label}
                                            {field.required && (
                                                <span className="text-destructive">
                                                    {' '}
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            id={`config-${key}`}
                                            name={`whatsapp_config[${driver}][${key}]`}
                                            type={
                                                field.type === 'password'
                                                    ? 'password'
                                                    : field.type === 'url'
                                                      ? 'url'
                                                      : 'text'
                                            }
                                            value={driverConfig[key] ?? ''}
                                            onChange={(e) =>
                                                setData('whatsapp_config', {
                                                    ...data.whatsapp_config,
                                                    [driver]: {
                                                        ...data.whatsapp_config[
                                                            driver
                                                        ],
                                                        [key]: e.target.value,
                                                    },
                                                })
                                            }
                                            placeholder={
                                                field.placeholder ?? ''
                                            }
                                        />
                                        {fieldError && (
                                            <p className="text-sm text-red-600">
                                                {fieldError}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                    </CardContent>
                    <CardFooter>
                        <Button disabled={processing}>Save</Button>
                    </CardFooter>
                </Card>
            </form>

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
                                        <span className="text-sm text-muted-foreground">
                                            {devicePhone}
                                        </span>
                                    )}
                                    {deviceLastConnected && (
                                        <span className="text-xs text-muted-foreground">
                                            Last connected:{' '}
                                            {new Date(
                                                deviceLastConnected,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                </>
                            ) : connectionStatus === 'connecting' ? (
                                <Badge variant="secondary">Connecting</Badge>
                            ) : connectionStatus === 'disconnected' ? (
                                <Badge variant="destructive">
                                    Disconnected
                                </Badge>
                            ) : null}
                        </div>

                        {pairingError && (
                            <p className="text-sm text-red-600">
                                {pairingError}
                            </p>
                        )}

                        {connectionStatus === 'connecting' &&
                            !qrCode &&
                            !pairingLoading && (
                                <p className="text-sm text-muted-foreground">
                                    Generating QR code...
                                </p>
                            )}

                        {qrCode && (
                            <div className="space-y-4">
                                <Separator />
                                <div className="space-y-3">
                                    <div className="inline-block rounded-lg border p-2">
                                        <img
                                            src={`data:image/png;base64,${qrCode}`}
                                            alt="QR Code"
                                            className="size-48"
                                        />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Scan this QR code with your WhatsApp app
                                        to connect your device.
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter className="gap-2">
                        {connectionStatus !== 'connected' && (
                            <Button
                                onClick={handlePair}
                                disabled={pairingLoading}
                            >
                                {pairingLoading ? 'Pairing...' : 'Pair Device'}
                            </Button>
                        )}
                        {showDisconnect && (
                            <Button variant="destructive" asChild>
                                <Link
                                    href={disconnectWhatsApp().url}
                                    method="delete"
                                    as="button"
                                >
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
                        Verify that the active WhatsApp driver is working
                        correctly.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        A health check will be performed on the active driver.
                    </p>
                </CardContent>
                <CardFooter>
                    <Button variant="secondary" asChild>
                        <Link
                            href={testWhatsApp.url()}
                            method="post"
                            as="button"
                        >
                            Test Connection
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}

WhatsApp.layout = {
    breadcrumbs: [{ title: 'WhatsApp settings', href: editWhatsApp() }],
};
