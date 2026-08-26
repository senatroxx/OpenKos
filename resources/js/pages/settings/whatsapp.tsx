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
import { t } from '@/lib/i18n';
import {
    edit as editWhatsApp,
    update as updateWhatsApp,
    test as testWhatsApp,
} from '@/routes/settings/whatsapp';
import type { Driver } from '@/types';

const normalizeDriver = (name: string | null | undefined): string => {
    if (!name) {
        return 'openkos/whatsapp-log';
    }

    if (name === 'log' || name === 'openkos/log') {
        return 'openkos/whatsapp-log';
    }

    if (name === 'fonnte') {
        return 'openkos/fonnte';
    }

    return name;
};

export default function WhatsApp({
    drivers = [],
    settings,
}: {
    drivers: Driver[];
    settings: {
        whatsapp_driver: string | null;
        whatsapp_config: Record<string, Record<string, string>> | null;
    };
}) {
    const initialDriver = normalizeDriver(settings.whatsapp_driver);

    const { data, setData, submit, processing, errors } = useForm<{
        whatsapp_driver: string;
        whatsapp_config: Record<string, Record<string, string>>;
    }>({
        whatsapp_driver: initialDriver,
        whatsapp_config: settings.whatsapp_config ?? {},
    });

    const activeDriverName = normalizeDriver(data.whatsapp_driver);
    const driver = activeDriverName;
    const currentDriver =
        drivers.find((d) => d.name === activeDriverName) ??
        drivers.find((d) => d.name === 'openkos/whatsapp-log') ??
        drivers[0];
    const fields = currentDriver?.configuration_schema ?? {};

    const rawDriverKey = activeDriverName
        .replace(/^openkos\//, '')
        .replace(/^whatsapp-/, '');
    const driverConfig =
        data.whatsapp_config[activeDriverName] ??
        data.whatsapp_config[rawDriverKey] ??
        {};

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(updateWhatsApp());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">
                    {t('WhatsApp settings')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Configure the active WhatsApp driver and its credentials.',
                    )}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('WhatsApp Driver')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Select the active WhatsApp driver and configure its credentials.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="whatsapp_driver">
                                    {t('Driver')}
                                </Label>
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
                                            <SelectItem
                                                key={d.name}
                                                value={d.name}
                                            >
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
                                <div className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                    {t(
                                        'Values saved below override environment defaults. Leave fields blank to use environment variable fallbacks.',
                                    )}
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
                                                {t(field.label)}
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
                                                            ...data
                                                                .whatsapp_config[
                                                                driver
                                                            ],
                                                            [key]: e.target
                                                                .value,
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
                            <Button disabled={processing}>{t('Save')}</Button>
                        </CardFooter>
                    </Card>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Test Connection')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Verify that the active WhatsApp driver is working correctly.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'A health check will be performed on the active driver.',
                            )}
                        </p>
                    </CardContent>
                    <CardFooter>
                        <Button variant="secondary" asChild>
                            <Link
                                href={testWhatsApp.url()}
                                method="post"
                                as="button"
                            >
                                {t('Test Connection')}
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    );
}

WhatsApp.layout = {
    breadcrumbs: [{ title: 'WhatsApp settings', href: editWhatsApp() }],
};
