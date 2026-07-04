import { LeaseOverview } from '@/components/features';
import type { Lease } from '@/types';
import { LeaseLayout } from './layout';

export default function LeaseWorkspace({ lease }: { lease: Lease }) {
    return (
        <LeaseLayout lease={lease} activeTab="overview">
            <LeaseOverview lease={lease} />
        </LeaseLayout>
    );
}
