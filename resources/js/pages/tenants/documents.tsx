import { PluginRegion } from '@/components/shared/plugin-region';
import type { WorkspaceTenant } from './layout';
import { TenantLayout } from './layout';
import DocumentsTab from './tabs/documents';

export default function TenantDocuments({ tenant }: { tenant: WorkspaceTenant }) {
    return (
        <TenantLayout tenant={tenant} activeTab="documents">
            <PluginRegion name="workspace-tab-documents">
                <DocumentsTab docs={tenant.documents ?? []} />
            </PluginRegion>
        </TenantLayout>
    );
}
