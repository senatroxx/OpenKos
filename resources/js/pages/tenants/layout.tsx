import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import type { WorkspaceTenant } from '@/types';

export function TenantLayout({
    tenant,
    activeTab,
    children,
}: {
    tenant: WorkspaceTenant;
    activeTab: string;
    children: ReactNode;
}) {
    return (
        <EntityWorkspaceLayout
            title={tenant.name}
            subtitle={tenant.phone ?? undefined}
            backRoute="/tenants"
            backLabel="All tenants"
        >
            <Head title={`${tenant.name} — Tenant`} />

            <WorkspaceTabs
                workspace="tenant"
                activeTab={activeTab}
                hrefParams={{ id: tenant.id }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/tenants/${tenant.id}`,
                    },
                    {
                        key: 'leases',
                        label: 'Leases',
                        href: `/tenants/${tenant.id}/leases`,
                    },
                    {
                        key: 'documents',
                        label: 'Documents',
                        href: `/tenants/${tenant.id}/documents`,
                    },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
