import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
    edit as editGeneral,
    update as updateGeneral,
} from '@/routes/settings/general';

export default function General({
    settings,
    timezone_list: timezoneList,
}: {
    settings: {
        site_name: string;
        country_code: string;
        locale: string;
        currency: string;
        timezone: string;
        lease_id_prefix: string;
        invoice_id_prefix: string;
    };
    timezone_list: string[];
}) {
    const { data, setData, submit, processing, errors } = useForm({
        site_name: settings.site_name,
        country_code: settings.country_code,
        locale: settings.locale,
        currency: settings.currency,
        timezone: settings.timezone,
        lease_id_prefix: settings.lease_id_prefix,
        invoice_id_prefix: settings.invoice_id_prefix,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(updateGeneral());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">General settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage application-wide settings.
                </p>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Site</CardTitle>
                            <CardDescription>
                                The name displayed throughout the application.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="site_name">Site name</Label>
                                <Input
                                    id="site_name"
                                    name="site_name"
                                    value={data.site_name}
                                    onChange={(e) =>
                                        setData('site_name', e.target.value)
                                    }
                                    maxLength={255}
                                    placeholder="OpenKOS"
                                    required
                                />
                                {errors.site_name && (
                                    <p className="text-sm text-red-600">
                                        {errors.site_name}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Localization</CardTitle>
                            <CardDescription>
                                Regional preferences for the application.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="country_code">Country</Label>
                                <Input
                                    id="country_code"
                                    name="country_code"
                                    value={data.country_code}
                                    onChange={(e) =>
                                        setData(
                                            'country_code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={2}
                                    className="font-mono uppercase"
                                    placeholder="ID"
                                    required
                                />
                                {errors.country_code && (
                                    <p className="text-sm text-red-600">
                                        {errors.country_code}
                                    </p>
                                )}
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="locale">Locale</Label>
                                <Input
                                    id="locale"
                                    name="locale"
                                    value={data.locale}
                                    onChange={(e) =>
                                        setData('locale', e.target.value)
                                    }
                                    maxLength={10}
                                    placeholder="id"
                                    required
                                />
                                {errors.locale && (
                                    <p className="text-sm text-red-600">
                                        {errors.locale}
                                    </p>
                                )}
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="currency">Currency</Label>
                                <Input
                                    id="currency"
                                    name="currency"
                                    value={data.currency}
                                    onChange={(e) =>
                                        setData(
                                            'currency',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={3}
                                    className="font-mono uppercase"
                                    placeholder="IDR"
                                    required
                                />
                                {errors.currency && (
                                    <p className="text-sm text-red-600">
                                        {errors.currency}
                                    </p>
                                )}
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <Select
                                    value={data.timezone}
                                    onValueChange={(value) =>
                                        setData('timezone', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {timezoneList.map((tz) => (
                                            <SelectItem key={tz} value={tz}>
                                                {tz}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.timezone && (
                                    <p className="text-sm text-red-600">
                                        {errors.timezone}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>References</CardTitle>
                            <CardDescription>
                                Prefixes used for auto-generated reference
                                numbers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="lease_id_prefix">
                                    Lease prefix
                                </Label>
                                <Input
                                    id="lease_id_prefix"
                                    name="lease_id_prefix"
                                    value={data.lease_id_prefix}
                                    onChange={(e) =>
                                        setData(
                                            'lease_id_prefix',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={10}
                                    className="font-mono uppercase"
                                    placeholder="LSX"
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Format:{' '}
                                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono">
                                        {data.lease_id_prefix}20260001
                                    </code>
                                </p>
                                {errors.lease_id_prefix && (
                                    <p className="text-sm text-red-600">
                                        {errors.lease_id_prefix}
                                    </p>
                                )}
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="invoice_id_prefix">
                                    Invoice prefix
                                </Label>
                                <Input
                                    id="invoice_id_prefix"
                                    name="invoice_id_prefix"
                                    value={data.invoice_id_prefix}
                                    onChange={(e) =>
                                        setData(
                                            'invoice_id_prefix',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={10}
                                    className="font-mono uppercase"
                                    placeholder="INV"
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Format:{' '}
                                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono">
                                        {data.invoice_id_prefix}20260001
                                    </code>
                                </p>
                                {errors.invoice_id_prefix && (
                                    <p className="text-sm text-red-600">
                                        {errors.invoice_id_prefix}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Button disabled={processing}>Save</Button>
                </div>
            </form>
        </div>
    );
}

General.layout = {
    breadcrumbs: [{ title: 'General settings', href: editGeneral() }],
};
