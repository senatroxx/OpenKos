import { PluginRegion } from '@/components/shared/plugin-region';
import type { WorkspaceTenant } from './layout';
import { TenantLayout } from './layout';
import LeasesTab from './tabs/leases';

export default function TenantLeases({ tenant }: { tenant: WorkspaceTenant }) {
    return (
        <TenantLayout tenant={tenant} activeTab="leases">
            <PluginRegion name="workspace-tab-leases">
                <LeasesTab leases={tenant.leases ?? []} />
            </PluginRegion>
        </TenantLayout>
    );
}
