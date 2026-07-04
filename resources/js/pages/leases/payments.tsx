import { PluginRegion } from '@/components/shared/plugin-region';
import type { Lease } from '@/types';
import { LeaseLayout } from './layout';
import PaymentsTab from './tabs/payments';

export default function LeasePayments({ lease }: { lease: Lease }) {
    return (
        <LeaseLayout lease={lease} activeTab="payments">
            <PluginRegion name="workspace-tab-payments">
                <PaymentsTab lease={lease} />
            </PluginRegion>
        </LeaseLayout>
    );
}
