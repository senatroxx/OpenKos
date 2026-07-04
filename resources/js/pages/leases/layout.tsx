import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import type { Lease } from '@/types';

export function LeaseLayout({
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
            backRoute="/leases"
            backLabel="All leases"
        >
            <Head title={`Lease #${lease.id} — Lease`} />

            <WorkspaceTabs
                workspace="lease"
                activeTab={activeTab}
                hrefParams={{ id: lease.id }}
                tabs={[
                    { key: 'overview', label: 'Overview', href: `/leases/${lease.id}` },
                    { key: 'payments', label: 'Payments', href: `/leases/${lease.id}/payments` },
                    { key: 'documents', label: 'Documents', href: `/leases/${lease.id}/documents` },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
