import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { LeaseOverview } from '@/components/features';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { PluginRegion } from '@/components/shared/plugin-region';
import { usePlatformTabs } from '@/lib/platform';
import type { Lease } from '@/types';
import DocumentsTab from './tabs/documents';
import PaymentsTab from './tabs/payments';

const TABS = ['Overview', 'Payments', 'Documents'] as const;

export default function LeaseWorkspace({ lease }: { lease: Lease }) {
    const platformTabs = usePlatformTabs('lease');
    const [tab, setTab] = useState<string>('Overview');
    const payments = lease.payments ?? [];
    const proofDocs = payments.flatMap((p) =>
        (p.proofs ?? []).map((pr) => ({ ...pr, payment: p })),
    );

    return (
        <EntityWorkspaceLayout
            title={`Lease #${lease.id}`}
            subtitle={lease.reference ?? undefined}
            backRoute="/leases"
            backLabel="All leases"
        >
            <Head title={`Lease #${lease.id} — Lease`} />

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

            {tab === 'Overview' && <LeaseOverview lease={lease} />}
            {tab === 'Payments' && <PluginRegion name="workspace-tab-payments"><PaymentsTab lease={lease} /></PluginRegion>}
            {tab === 'Documents' && <PluginRegion name="workspace-tab-documents"><DocumentsTab docs={proofDocs} /></PluginRegion>}
            {platformTabs.map(
                (t) =>
                    tab === t.key && (
                        <PluginRegion key={t.key} name={`workspace-tab-${t.key}`} />
                    ),
            )}
        </EntityWorkspaceLayout>
    );
}
