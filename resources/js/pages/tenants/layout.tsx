import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import type { UnitWithProperty, TenantDocument, TenantInfo } from '@/types';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    unit: UnitWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

export type WorkspaceTenant = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
    deleted_at: string | null;
    active_leases_count?: number;
    leases?: Lease[];
    documents?: TenantDocument[];
};

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
                    { key: 'overview', label: 'Overview', href: `/tenants/${tenant.id}` },
                    { key: 'leases', label: 'Leases', href: `/tenants/${tenant.id}/leases` },
                    { key: 'documents', label: 'Documents', href: `/tenants/${tenant.id}/documents` },
                ]}
            />

            {children}
        </EntityWorkspaceLayout>
    );
}
