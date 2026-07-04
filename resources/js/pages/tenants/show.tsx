import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { TenantOverview } from '@/components/features';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { PluginRegion } from '@/components/shared/plugin-region';
import { usePlatformTabs } from '@/lib/platform';
import type { RoomWithProperty, TenantDocument, TenantInfo } from '@/types';
import DocumentsTab from './tabs/documents';
import LeasesTab from './tabs/leases';

type Lease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    room: RoomWithProperty | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

type WorkspaceTenant = {
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

const TABS = ['Overview', 'Leases', 'Documents'] as const;

export default function TenantWorkspace({ tenant }: { tenant: WorkspaceTenant }) {
    const platformTabs = usePlatformTabs('tenant');
    const [tab, setTab] = useState<string>('Overview');
    const leases = tenant.leases ?? [];
    const docs = tenant.documents ?? [];

    return (
        <EntityWorkspaceLayout
            title={tenant.name}
            subtitle={tenant.phone ?? undefined}
            backRoute="/tenants"
            backLabel="All tenants"
        >
            <Head title={`${tenant.name} — Tenant`} />

            <PluginRegion name="workspace-tabs-before" />

            <div className="mb-6 border-b">
                <nav className="-mb-px flex gap-6">
                    {[
                        ...TABS.map((t) => ({ key: t, label: t })),
                        ...platformTabs,
                    ].map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                tab === t.key
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />

            {tab === 'Overview' && <TenantOverview tenant={tenant} />}
            {tab === 'Leases' && <PluginRegion name="workspace-tab-leases"><LeasesTab leases={leases} /></PluginRegion>}
            {tab === 'Documents' && <PluginRegion name="workspace-tab-documents"><DocumentsTab docs={docs} /></PluginRegion>}
            {platformTabs.map(
                (t) =>
                    tab === t.key && (
                        <PluginRegion key={t.key} name={`workspace-tab-${t.key}`} />
                    ),
            )}
        </EntityWorkspaceLayout>
    );
}
