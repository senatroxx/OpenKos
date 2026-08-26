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
    edit as editMail,
    update as updateMail,
    test as testMail,
} from '@/routes/settings/mail';
import type { Driver } from '@/types';

interface MailConfig {
    driver?: string;
    host?: string;
    port?: number | string;
    username?: string;
    encryption?: string;
    from_address?: string;
    from_name?: string;
    from?: { address?: string; name?: string };
    drivers?: Record<string, Record<string, string>>;
}

export default function Mail({
    drivers = [],
    settings,
}: {
    drivers?: Driver[];
    settings: {
        mail_config: MailConfig | null;
        credential_status?: Record<string, boolean>;
    };
}) {
    const config = settings.mail_config ?? {};

    const initialDriver = config.driver ?? 'openkos/smtp';

    const { data, setData, submit, processing, errors } = useForm({
        mail_config: {
            driver: initialDriver,
            host: config.host ?? '',
            port: config.port != null ? String(config.port) : '587',
            username: config.username ?? '',
            password: '',
            encryption: config.encryption ?? 'null',
            from_address: config.from_address ?? config.from?.address ?? '',
            from_name: config.from_name ?? config.from?.name ?? '',
            drivers: config.drivers ?? {},
        },
    });

    const activeDriverName = data.mail_config.driver;
    const currentDriver =
        drivers.find((d) => d.name === activeDriverName) ??
        drivers.find((d) => d.name === 'openkos/smtp');
    const fields = currentDriver?.configuration_schema ?? {};
    const rawDriverKey = activeDriverName.replace(/^openkos\//, '');
    const driverConfig =
        data.mail_config.drivers[activeDriverName] ??
        data.mail_config.drivers[rawDriverKey] ??
        {};
    const hasSavedCredentials =
        settings.credential_status?.[activeDriverName] ??
        settings.credential_status?.[rawDriverKey] ??
        false;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(updateMail());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">{t('Mail settings')}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Configure the active mail driver, sender address, and credentials.',
                    )}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Mail Driver')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Select the active mail transport driver and configure its settings.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {drivers.length > 0 && (
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_config_driver">
                                        {t('Driver')}
                                    </Label>
                                    <Select
                                        value={data.mail_config.driver}
                                        onValueChange={(value) =>
                                            setData('mail_config', {
                                                ...data.mail_config,
                                                driver: value,
                                            })
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
                                    {errors['mail_config.driver'] && (
                                        <p className="text-sm text-red-600">
                                            {errors['mail_config.driver']}
                                        </p>
                                    )}
                                </div>
                            )}

                            {Object.keys(fields).length > 0 && (
                                <div className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                    {t(
                                        'Values saved below override environment defaults. Leave password fields blank to preserve saved values or use environment fallbacks.',
                                    )}
                                </div>
                            )}

                            {Object.keys(fields).length > 0 ? (
                                Object.entries(fields).map(([key, field]) => {
                                    const errorKey =
                                        `mail_config.drivers.${activeDriverName}.${key}` as keyof typeof errors;

                                    if (
                                        field.type === 'select' &&
                                        field.options
                                    ) {
                                        return (
                                            <div
                                                key={key}
                                                className="grid max-w-xs gap-2"
                                            >
                                                <Label
                                                    htmlFor={`mail_config_${key}`}
                                                >
                                                    {t(field.label)}
                                                    {field.required && (
                                                        <span className="text-destructive">
                                                            {' '}
                                                            *
                                                        </span>
                                                    )}
                                                </Label>
                                                <Select
                                                    value={
                                                        driverConfig[key] ||
                                                        'null'
                                                    }
                                                    onValueChange={(val) =>
                                                        setData('mail_config', {
                                                            ...data.mail_config,
                                                            drivers: {
                                                                ...data
                                                                    .mail_config
                                                                    .drivers,
                                                                [activeDriverName]:
                                                                    {
                                                                        ...driverConfig,
                                                                        [key]: val,
                                                                    },
                                                            },
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue
                                                            placeholder={t(
                                                                'Select...',
                                                            )}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {field.options.map(
                                                            (opt) => (
                                                                <SelectItem
                                                                    key={
                                                                        opt.value
                                                                    }
                                                                    value={
                                                                        opt.value
                                                                    }
                                                                >
                                                                    {t(
                                                                        opt.label,
                                                                    )}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {errors[errorKey] && (
                                                    <p className="text-sm text-red-600">
                                                        {errors[errorKey]}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    }

                                    return (
                                        <div
                                            key={key}
                                            className="grid max-w-xs gap-2"
                                        >
                                            <Label
                                                htmlFor={`mail_config_${key}`}
                                            >
                                                {t(field.label)}
                                                {field.required && (
                                                    <span className="text-destructive">
                                                        {' '}
                                                        *
                                                    </span>
                                                )}
                                            </Label>
                                            <Input
                                                id={`mail_config_${key}`}
                                                name={`mail_config[drivers][${activeDriverName}][${key}]`}
                                                type={
                                                    field.type === 'password'
                                                        ? 'password'
                                                        : field.type ===
                                                            'number'
                                                          ? 'number'
                                                          : 'text'
                                                }
                                                value={driverConfig[key] ?? ''}
                                                onChange={(e) =>
                                                    setData('mail_config', {
                                                        ...data.mail_config,
                                                        drivers: {
                                                            ...data.mail_config
                                                                .drivers,
                                                            [activeDriverName]:
                                                                {
                                                                    ...driverConfig,
                                                                    [key]: e
                                                                        .target
                                                                        .value,
                                                                },
                                                        },
                                                    })
                                                }
                                                placeholder={
                                                    field.type === 'password' &&
                                                    hasSavedCredentials
                                                        ? '••••••••••••'
                                                        : (field.placeholder ??
                                                          '')
                                                }
                                            />
                                            {field.type === 'password' &&
                                                hasSavedCredentials && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'A value is saved. Leave blank to keep it.',
                                                        )}
                                                    </p>
                                                )}
                                            {errors[errorKey] && (
                                                <p className="text-sm text-red-600">
                                                    {errors[errorKey]}
                                                </p>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'This driver has no additional settings in this form.',
                                    )}
                                </p>
                            )}

                            <div className="space-y-4 border-t pt-4">
                                <h3 className="text-sm font-medium">
                                    {t('Default Sender Information')}
                                </h3>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="mail_config[from_address]">
                                        {t('From Address')}
                                    </Label>
                                    <Input
                                        id="mail_config[from_address]"
                                        name="mail_config[from_address]"
                                        type="email"
                                        value={data.mail_config.from_address}
                                        onChange={(e) =>
                                            setData('mail_config', {
                                                ...data.mail_config,
                                                from_address: e.target.value,
                                            })
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
                                        {t('From Name')}
                                    </Label>
                                    <Input
                                        id="mail_config[from_name]"
                                        name="mail_config[from_name]"
                                        type="text"
                                        value={data.mail_config.from_name}
                                        onChange={(e) =>
                                            setData('mail_config', {
                                                ...data.mail_config,
                                                from_name: e.target.value,
                                            })
                                        }
                                        placeholder="OpenKOS"
                                    />
                                    {errors['mail_config.from_name'] && (
                                        <p className="text-sm text-red-600">
                                            {errors['mail_config.from_name']}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button disabled={processing}>{t('Save')}</Button>
                        </CardFooter>
                    </Card>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Test Mail Connection')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Verify that the active mail driver is configured correctly.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'A connection health check will be performed on the active mail driver.',
                            )}
                        </p>
                    </CardContent>
                    <CardFooter>
                        <Button variant="secondary" asChild>
                            <Link
                                href={testMail.url()}
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

Mail.layout = {
    breadcrumbs: [{ title: 'Mail settings', href: editMail() }],
};
