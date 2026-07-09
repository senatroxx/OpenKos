import { TenantOverview } from '@/components/features';
import type { WorkspaceTenant } from '@/types';
import { TenantLayout } from './layout';

export default function TenantWorkspace({
    tenant,
}: {
    tenant: WorkspaceTenant;
}) {
    return (
        <TenantLayout tenant={tenant} activeTab="overview">
            <TenantOverview tenant={tenant} />
        </TenantLayout>
    );
}
