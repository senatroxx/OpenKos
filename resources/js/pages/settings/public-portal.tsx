import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { InputError } from '@/components/shared';
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
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import publicPortal from '@/routes/settings/public-portal';
import socialImage from '@/routes/settings/public-portal/social-image';
import type { PublicPortalSettingsPageProps } from '@/types';

export default function PublicPortal({
    settings,
    resolved,
    socialImageUrl,
    hasSocialImage,
}: PublicPortalSettingsPageProps) {
    const form = useForm(settings);
    const [uploading, setUploading] = useState(false);
    const [imageError, setImageError] = useState<string | undefined>();

    function submitImage(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setUploading(true);
        setImageError(undefined);

        router.post(socialImage.update.url(), new FormData(event.currentTarget), {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setImageError(errors.file),
            onFinish: () => setUploading(false),
        });
    }

    function removeImage(): void {
        setUploading(true);
        setImageError(undefined);

        router.delete(socialImage.destroy.url(), {
            preserveScroll: true,
            onError: (errors) => setImageError(errors.file),
            onFinish: () => setUploading(false),
        });
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">{t('Public Portal')}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t('Set the public identity and sharing metadata for your storefront.')}
                </p>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.submit(publicPortal.update(), { preserveScroll: true });
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>{t('SEO and sharing')}</CardTitle>
                        <CardDescription>
                            {t('Leave a field blank to use the existing OpenKOS default.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="public_site_name">
                                {t('Public business name')}
                            </Label>
                            <Input
                                id="public_site_name"
                                value={form.data.public_site_name}
                                placeholder={resolved.siteName}
                                onChange={(event) =>
                                    form.setData('public_site_name', event.target.value)
                                }
                                maxLength={255}
                            />
                            <p className="text-sm text-muted-foreground">
                                {t('Leave blank to use “:name” from General settings. This does not change the admin application name.', { name: resolved.siteName })}
                            </p>
                            <InputError message={form.errors.public_site_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="public_homepage_title">
                                {t('Homepage SEO title')}
                            </Label>
                            <Input
                                id="public_homepage_title"
                                value={form.data.public_homepage_title}
                                placeholder={resolved.homepageTitle}
                                onChange={(event) =>
                                    form.setData('public_homepage_title', event.target.value)
                                }
                                maxLength={255}
                            />
                            <InputError message={form.errors.public_homepage_title} />
                            <p className="text-sm text-muted-foreground">
                                {t('Leave blank to use the default title.')}
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="public_homepage_description">
                                {t('Homepage meta description')}
                            </Label>
                            <Textarea
                                id="public_homepage_description"
                                value={form.data.public_homepage_description}
                                placeholder={resolved.homepageDescription}
                                onChange={(event) =>
                                    form.setData(
                                        'public_homepage_description',
                                        event.target.value,
                                    )
                                }
                                maxLength={500}
                                rows={4}
                            />
                            <InputError message={form.errors.public_homepage_description} />
                            <p className="text-sm text-muted-foreground">
                                {t('Leave blank to use the default description.')}
                            </p>
                        </div>

                        <Button disabled={form.processing}>{t('Save')}</Button>
                    </CardContent>
                </Card>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle>{t('Default social sharing image')}</CardTitle>
                    <CardDescription>
                        {t('This image is used when a public page does not have a more specific image.')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {hasSocialImage && socialImageUrl ? (
                        <img
                            src={socialImageUrl}
                            alt={t('Current social sharing image')}
                            className="max-h-48 w-full rounded-md border object-contain"
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('No custom social sharing image is configured.')}
                        </p>
                    )}
                    <form onSubmit={submitImage} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="social-image-file">{t('Upload image')}</Label>
                            <Input
                                id="social-image-file"
                                name="file"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('JPG, PNG, or WebP, 2 MB maximum.')}
                            </p>
                            <InputError message={imageError} />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={uploading}>
                                {uploading ? t('Uploading...') : t('Upload image')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!hasSocialImage || uploading}
                                onClick={removeImage}
                            >
                                {t('Restore default')}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

PublicPortal.layout = {
    breadcrumbs: [{ title: 'Public Portal', href: publicPortal.edit() }],
};
