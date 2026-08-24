import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AppearanceTabs } from '@/components/features';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    edit as editGeneral,
    update as updateGeneral,
} from '@/routes/settings/general';
import {
    destroy as destroyBranding,
    update as updateBranding,
} from '@/routes/settings/general/branding';

type BrandingAsset = 'logo' | 'favicon';

export default function General({
    settings,
    timezone_list: timezoneList,
}: {
    settings: {
        site_name: string;
        country_code: string;
        locale: string;
        currency: string;
        supported_currencies: string[];
        timezone: string;
        lease_id_prefix: string;
        invoice_id_prefix: string;
        invoice_pdf_enabled: boolean;
    };
    timezone_list: string[];
}) {
    const { app, branding } = usePage<{
        app: { currency_scales: Record<string, number> };
        branding: {
            logoUrl: string;
            faviconUrl: string;
            hasCustomLogo: boolean;
            hasCustomFavicon: boolean;
            hasConfiguredLogo: boolean;
            hasConfiguredFavicon: boolean;
        };
    }>().props;
    const currencyOptions = Object.keys(app.currency_scales).sort();
    const currencyNames = new Intl.DisplayNames(['en'], { type: 'currency' });

    function currencyLabel(currency: string): string {
        return `${currency} — ${currencyNames.of(currency) ?? currency}`;
    }
    const [uploadingBranding, setUploadingBranding] =
        useState<BrandingAsset | null>(null);
    const [brandingErrors, setBrandingErrors] = useState<
        Record<string, string>
    >({});

    const siteForm = useForm({
        site_name: settings.site_name,
    });

    const localizationForm = useForm({
        country_code: settings.country_code,
        locale: settings.locale,
        currency: settings.currency,
        supported_currencies: settings.supported_currencies,
        timezone: settings.timezone,
    });

    function setDefaultCurrency(currency: string): void {
        localizationForm.setData((current) => ({
            ...current,
            currency,
            supported_currencies: current.supported_currencies.includes(
                currency,
            )
                ? current.supported_currencies
                : [...current.supported_currencies, currency],
        }));
    }

    function toggleSupportedCurrency(currency: string, checked: boolean): void {
        if (!checked && currency === localizationForm.data.currency) {
            return;
        }

        localizationForm.setData(
            'supported_currencies',
            checked
                ? Array.from(
                      new Set([
                          ...localizationForm.data.supported_currencies,
                          currency,
                      ]),
                  )
                : localizationForm.data.supported_currencies.filter(
                      (item) => item !== currency,
                  ),
        );
    }

    const referenceForm = useForm({
        lease_id_prefix: settings.lease_id_prefix,
        invoice_id_prefix: settings.invoice_id_prefix,
    });

    const invoicePdfForm = useForm({
        invoice_pdf_enabled: settings.invoice_pdf_enabled,
    });

    function uploadBranding(
        event: FormEvent<HTMLFormElement>,
        asset: BrandingAsset,
    ): void {
        event.preventDefault();

        const form = event.currentTarget;

        setUploadingBranding(asset);
        setBrandingErrors({});

        router.post(updateBranding.url({ asset }), new FormData(form), {
            preserveScroll: true,
            onError: (errors) => setBrandingErrors(errors),
            onFinish: () => {
                setUploadingBranding(null);
                form.reset();
            },
        });
    }

    function removeBranding(asset: BrandingAsset): void {
        setUploadingBranding(asset);
        setBrandingErrors({});

        router.delete(destroyBranding.url({ asset }), {
            preserveScroll: true,
            onError: (errors) => setBrandingErrors(errors),
            onFinish: () => setUploadingBranding(null),
        });
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">General settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage application-wide settings.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Branding</CardTitle>
                    <CardDescription>
                        Customize the logo and favicon used by this
                        installation.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 lg:grid-cols-2">
                    {(
                        [
                            {
                                asset: 'logo',
                                title: 'Website logo',
                                description:
                                    'Shown in application navigation and authentication pages.',
                                url: branding.logoUrl,
                                hasCustom: branding.hasCustomLogo,
                                hasConfigured: branding.hasConfiguredLogo,
                                accept: '.jpg,.jpeg,.png,.webp',
                                formats: 'JPG, PNG, or WebP · 2 MB maximum',
                            },
                            {
                                asset: 'favicon',
                                title: 'Browser favicon',
                                description:
                                    'Shown in browser tabs and bookmarks.',
                                url: branding.faviconUrl,
                                hasCustom: branding.hasCustomFavicon,
                                hasConfigured: branding.hasConfiguredFavicon,
                                accept: '.png,.ico',
                                formats: 'PNG or ICO · 512 KB maximum',
                            },
                        ] as const
                    ).map((item) => (
                        <div
                            key={item.asset}
                            className="space-y-4 rounded-lg border p-4"
                        >
                            <div className="flex items-center gap-4">
                                <div className="flex size-20 items-center justify-center rounded-md border bg-muted/30 p-2">
                                    <img
                                        src={item.url}
                                        alt={`${item.title} preview`}
                                        className="size-full object-contain"
                                    />
                                </div>
                                <div>
                                    <h3 className="font-medium">
                                        {item.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {item.hasCustom
                                            ? 'Using custom asset'
                                            : 'Using bundled default'}
                                    </p>
                                </div>
                            </div>

                            <form
                                onSubmit={(event) =>
                                    uploadBranding(event, item.asset)
                                }
                                className="space-y-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor={`${item.asset}-file`}>
                                        Upload replacement
                                    </Label>
                                    <Input
                                        id={`${item.asset}-file`}
                                        name="file"
                                        type="file"
                                        accept={item.accept}
                                        required
                                        aria-describedby={`${item.asset}-formats`}
                                    />
                                    <p
                                        id={`${item.asset}-formats`}
                                        className="text-xs text-muted-foreground"
                                    >
                                        {item.formats}
                                    </p>
                                    <InputError message={brandingErrors.file} />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={uploadingBranding !== null}
                                    >
                                        {uploadingBranding === item.asset
                                            ? 'Uploading...'
                                            : 'Upload'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            !item.hasConfigured ||
                                            uploadingBranding !== null
                                        }
                                        onClick={() =>
                                            removeBranding(item.asset)
                                        }
                                    >
                                        Restore default
                                    </Button>
                                </div>
                            </form>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                <div className="space-y-6">
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
                                    The name displayed throughout the
                                    application.
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
                                <Button disabled={siteForm.processing}>
                                    Save
                                </Button>
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
                                    <Label htmlFor="country_code">
                                        Country
                                    </Label>
                                    <Input
                                        id="country_code"
                                        name="country_code"
                                        value={
                                            localizationForm.data.country_code
                                        }
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
                                            {
                                                localizationForm.errors
                                                    .country_code
                                            }
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

                                <div className="grid max-w-md gap-2">
                                    <Label htmlFor="currency">
                                        Default currency
                                    </Label>
                                    <Select
                                        value={localizationForm.data.currency}
                                        onValueChange={setDefaultCurrency}
                                    >
                                        <SelectTrigger id="currency">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencyOptions.map((currency) => (
                                                <SelectItem
                                                    key={currency}
                                                    value={currency}
                                                >
                                                    {currencyLabel(currency)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {localizationForm.errors.currency && (
                                        <p className="text-sm text-red-600">
                                            {localizationForm.errors.currency}
                                        </p>
                                    )}
                                </div>

                                <div className="grid max-w-md gap-2">
                                    <div>
                                        <Label>Supported currencies</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Available for new pricing and
                                            billing rates. Existing records keep
                                            their original currency.
                                        </p>
                                    </div>
                                    <div className="grid max-h-72 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                        {currencyOptions.map((currency) => {
                                            const isSupported =
                                                localizationForm.data.supported_currencies.includes(
                                                    currency,
                                                );
                                            const isDefault =
                                                localizationForm.data
                                                    .currency === currency;

                                            return (
                                                <label
                                                    key={currency}
                                                    className="flex items-start gap-2 rounded-md p-2 text-sm hover:bg-muted/50"
                                                >
                                                    <Checkbox
                                                        checked={isSupported}
                                                        disabled={isDefault}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleSupportedCurrency(
                                                                currency,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <span>
                                                        <span className="block font-mono font-medium">
                                                            {currency}
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            {currencyNames.of(
                                                                currency,
                                                            ) ?? currency}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                    <InputError
                                        message={
                                            localizationForm.errors
                                                .supported_currencies
                                        }
                                    />
                                </div>

                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Select
                                        value={localizationForm.data.timezone}
                                        onValueChange={(value) =>
                                            localizationForm.setData(
                                                'timezone',
                                                value,
                                            )
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
                </div>

                <div className="space-y-6">
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
                            invoicePdfForm.transform((data) => ({
                                invoice_pdf_enabled: data.invoice_pdf_enabled
                                    ? '1'
                                    : '0',
                            }));
                            invoicePdfForm.submit(updateGeneral());
                        }}
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Invoice PDFs</CardTitle>
                                <CardDescription>
                                    PDF generation runs in the queue and needs a
                                    running worker when enabled.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <Switch
                                        id="invoice_pdf_enabled"
                                        checked={
                                            invoicePdfForm.data
                                                .invoice_pdf_enabled
                                        }
                                        onCheckedChange={(checked) =>
                                            invoicePdfForm.setData(
                                                'invoice_pdf_enabled',
                                                checked,
                                            )
                                        }
                                    />
                                    <Label htmlFor="invoice_pdf_enabled">
                                        {invoicePdfForm.data.invoice_pdf_enabled
                                            ? 'Invoice PDF generation is enabled'
                                            : 'Invoice PDF generation is disabled'}
                                    </Label>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {invoicePdfForm.data.invoice_pdf_enabled
                                        ? 'Invoice PDFs are generated in the background, stored privately, and reused for downloads and supported reminders.'
                                        : 'Invoices remain available as web pages. Use the browser print or save-as-PDF flow; reminders include the invoice link without an attachment.'}
                                </p>
                                <Button disabled={invoicePdfForm.processing}>
                                    Save
                                </Button>
                            </CardContent>
                        </Card>
                    </form>

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
                                        value={
                                            referenceForm.data.lease_id_prefix
                                        }
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
                                            {
                                                referenceForm.errors
                                                    .lease_id_prefix
                                            }
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
                                        value={
                                            referenceForm.data.invoice_id_prefix
                                        }
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
                                            {
                                                referenceForm.data
                                                    .invoice_id_prefix
                                            }
                                            20260001
                                        </code>
                                    </p>
                                    {referenceForm.errors.invoice_id_prefix && (
                                        <p className="text-sm text-red-600">
                                            {
                                                referenceForm.errors
                                                    .invoice_id_prefix
                                            }
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
            </div>
        </div>
    );
}

General.layout = {
    breadcrumbs: [{ title: 'General settings', href: editGeneral() }],
};
