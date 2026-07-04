import { PluginRegion } from '@/components/shared/plugin-region';
import type { Lease } from '@/types';
import { LeaseLayout } from './layout';
import DocumentsTab from './tabs/documents';

export default function LeaseDocuments({ lease }: { lease: Lease }) {
    const payments = lease.payments ?? [];
    const proofDocs = payments.flatMap((p) =>
        (p.proofs ?? []).map((pr) => ({ ...pr, payment: p })),
    );

    return (
        <LeaseLayout lease={lease} activeTab="documents">
            <PluginRegion name="workspace-tab-documents">
                <DocumentsTab docs={proofDocs} />
            </PluginRegion>
        </LeaseLayout>
    );
}
