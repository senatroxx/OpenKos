import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { PublicPortalWorkspaceLayout } from '@/components/features/public-portal/public-portal-workspace-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { t } from '@/lib/i18n';
import publicStorefront from '@/routes/public/portal';
import publicPortal from '@/routes/public-portal';
import type { PublicPortalOverviewPageProps } from '@/types';

export default function Overview({
    resolved,
    hasSocialImage,
}: PublicPortalOverviewPageProps) {
    return (
        <PublicPortalWorkspaceLayout resolved={resolved} activeTab="overview">
            <div className="max-w-3xl space-y-6">
                <div>
                    <h2 className="text-lg font-medium">
                        {t('Public Portal')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Manage your public storefront and how it appears to visitors.',
                        )}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Public identity')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Business name')}
                                </dt>
                                <dd className="font-medium">
                                    {resolved.siteName}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Homepage title')}
                                </dt>
                                <dd className="font-medium">
                                    {resolved.homepageTitle}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('Sharing image')}
                                </dt>
                                <dd className="font-medium">
                                    {hasSocialImage
                                        ? t('Custom')
                                        : t('Default / listing image')}
                                </dd>
                            </div>
                        </dl>
                        <Button variant="outline" asChild>
                            <Link href={publicStorefront.index().url}>
                                {t('View public site')}
                                <ExternalLink />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Configuration')}</CardTitle>
                    </CardHeader>
                    <CardContent className="flex items-center justify-between gap-4">
                        <div>
                            <h3 className="font-medium">
                                {t('SEO & Sharing')}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'Control search metadata and social sharing appearance.',
                                )}
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={publicPortal.seo().url}>
                                {t('Manage')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PublicPortalWorkspaceLayout>
    );
}
