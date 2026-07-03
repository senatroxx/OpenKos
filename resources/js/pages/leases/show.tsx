import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { LeaseOverview } from '@/components/features';
import { PluginRegion } from '@/components/shared/plugin-region';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import type { Lease } from '@/types';

const TABS = ['Overview', 'Payments', 'Documents'] as const;

export default function LeaseWorkspace({ lease }: { lease: Lease }) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Overview');

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
                    {TABS.map((t) => (
                        <button
                            key={t}
                            type="button"
                            onClick={() => setTab(t)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                tab === t
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t}
                        </button>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />

            {tab === 'Overview' && <LeaseOverview lease={lease} />}
            {tab === 'Payments' && (
                <PluginRegion name="workspace-tab-payments">
                    <p className="text-sm text-muted-foreground">Payments list coming soon.</p>
                </PluginRegion>
            )}
            {tab === 'Documents' && (
                <PluginRegion name="workspace-tab-documents">
                    <p className="text-sm text-muted-foreground">Documents coming soon.</p>
                </PluginRegion>
            )}
        </EntityWorkspaceLayout>
    );
}
