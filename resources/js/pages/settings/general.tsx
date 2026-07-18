import { useForm } from '@inertiajs/react';
import { AppearanceTabs } from '@/components/features';
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
    const siteForm = useForm({
        site_name: settings.site_name,
    });

    const localizationForm = useForm({
        country_code: settings.country_code,
        locale: settings.locale,
        currency: settings.currency,
        timezone: settings.timezone,
    });

    const referenceForm = useForm({
        lease_id_prefix: settings.lease_id_prefix,
        invoice_id_prefix: settings.invoice_id_prefix,
    });

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">General settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage application-wide settings.
                </p>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    siteForm.submit(updateGeneral());
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Site</CardTitle>
                        <CardDescription>
                            The name displayed throughout the application.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="site_name">Site name</Label>
                            <Input
                                id="site_name"
                                name="site_name"
                                value={siteForm.data.site_name}
                                onChange={(e) =>
                                    siteForm.setData(
                                        'site_name',
                                        e.target.value,
                                    )
                                }
                                maxLength={255}
                                placeholder="OpenKOS"
                                required
                            />
                            {siteForm.errors.site_name && (
                                <p className="text-sm text-red-600">
                                    {siteForm.errors.site_name}
                                </p>
                            )}
                        </div>
                        <Button disabled={siteForm.processing}>Save</Button>
                    </CardContent>
                </Card>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    localizationForm.submit(updateGeneral());
                }}
            >
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
                                value={localizationForm.data.country_code}
                                onChange={(e) =>
                                    localizationForm.setData(
                                        'country_code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={2}
                                className="font-mono uppercase"
                                placeholder="ID"
                                required
                            />
                            {localizationForm.errors.country_code && (
                                <p className="text-sm text-red-600">
                                    {localizationForm.errors.country_code}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="locale">Locale</Label>
                            <Input
                                id="locale"
                                name="locale"
                                value={localizationForm.data.locale}
                                onChange={(e) =>
                                    localizationForm.setData(
                                        'locale',
                                        e.target.value,
                                    )
                                }
                                maxLength={10}
                                placeholder="id"
                                required
                            />
                            {localizationForm.errors.locale && (
                                <p className="text-sm text-red-600">
                                    {localizationForm.errors.locale}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="currency">Currency</Label>
                            <Input
                                id="currency"
                                name="currency"
                                value={localizationForm.data.currency}
                                onChange={(e) =>
                                    localizationForm.setData(
                                        'currency',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={3}
                                className="font-mono uppercase"
                                placeholder="IDR"
                                required
                            />
                            {localizationForm.errors.currency && (
                                <p className="text-sm text-red-600">
                                    {localizationForm.errors.currency}
                                </p>
                            )}
                        </div>

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="timezone">Timezone</Label>
                            <Select
                                value={localizationForm.data.timezone}
                                onValueChange={(value) =>
                                    localizationForm.setData('timezone', value)
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
                            {localizationForm.errors.timezone && (
                                <p className="text-sm text-red-600">
                                    {localizationForm.errors.timezone}
                                </p>
                            )}
                        </div>

                        <Button disabled={localizationForm.processing}>
                            Save
                        </Button>
                    </CardContent>
                </Card>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle>Appearance</CardTitle>
                    <CardDescription>
                        Choose how the application looks for you.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AppearanceTabs />
                </CardContent>
            </Card>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    referenceForm.submit(updateGeneral());
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>References</CardTitle>
                        <CardDescription>
                            Prefixes used for auto-generated reference numbers.
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
                                value={referenceForm.data.lease_id_prefix}
                                onChange={(e) =>
                                    referenceForm.setData(
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
                                    {referenceForm.data.lease_id_prefix}
                                    20260001
                                </code>
                            </p>
                            {referenceForm.errors.lease_id_prefix && (
                                <p className="text-sm text-red-600">
                                    {referenceForm.errors.lease_id_prefix}
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
                                value={referenceForm.data.invoice_id_prefix}
                                onChange={(e) =>
                                    referenceForm.setData(
                                        'invoice_id_prefix',
                                        e.target.value.toUpperCase(),
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
                                    {referenceForm.data.invoice_id_prefix}
                                    20260001
                                </code>
                            </p>
                            {referenceForm.errors.invoice_id_prefix && (
                                <p className="text-sm text-red-600">
                                    {referenceForm.errors.invoice_id_prefix}
                                </p>
                            )}
                        </div>

                        <Button disabled={referenceForm.processing}>
                            Save
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </div>
    );
}

General.layout = {
    breadcrumbs: [{ title: 'General settings', href: editGeneral() }],
};
