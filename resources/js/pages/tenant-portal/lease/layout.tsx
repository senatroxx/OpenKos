import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { history, index, invoices, show } from '@/routes/portal/lease';
import type { Lease } from '@/types';

export function TenantLeaseLayout({
    lease,
    activeTab,
    children,
}: {
    lease: Lease;
    activeTab: string;
    children: ReactNode;
}) {
    return (
        <EntityWorkspaceLayout
            title={`Lease #${lease.id}`}
            subtitle={lease.reference ?? undefined}
            backRoute={index.url()}
            backLabel="All leases"
        >
            <Head title={`Lease #${lease.id}`} />

            <WorkspaceTabs
                workspace="tenant-portal-lease"
                activeTab={activeTab}
                tabs={[
                    { key: 'overview', label: 'Overview', href: show.url(lease) },
                    { key: 'invoices', label: 'Invoices', href: invoices.url(lease) },
                    { key: 'history', label: 'History', href: history.url(lease) },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
