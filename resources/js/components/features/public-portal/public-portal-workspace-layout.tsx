import { Head } from '@inertiajs/react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { dashboard } from '@/routes';
import publicPortal from '@/routes/public-portal';
import type { PublicPortalWorkspaceLayoutProps } from '@/types';

export function PublicPortalWorkspaceLayout({
    resolved,
    activeTab,
    children,
}: PublicPortalWorkspaceLayoutProps) {
    return (
        <EntityWorkspaceLayout
            title="Public Portal"
            subtitle={resolved.siteName}
            backRoute={dashboard().url}
            backLabel="Dashboard"
        >
            <Head title="Public Portal" />

            <WorkspaceTabs
                workspace="public-portal"
                activeTab={activeTab}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: publicPortal.overview().url,
                    },
                    {
                        key: 'seo',
                        label: 'SEO & Sharing',
                        href: publicPortal.seo().url,
                    },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
