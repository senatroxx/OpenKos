import { FileText } from 'lucide-react';
import type { Payment } from '@/types';

function formatPeriod(periodStart: string): string {
    return new Date(periodStart).toLocaleDateString('id-ID', { year: 'numeric', month: 'long' });
}

type ProofDoc = {
    id: number;
    original_name: string;
    mime_type: string;
    payment: Payment;
};

export default function DocumentsTab({ docs }: { docs: ProofDoc[] }) {
    if (docs.length === 0) {
        return <p className="text-sm text-muted-foreground">No documents.</p>;
    }

    return (
        <div className="space-y-2">
            {docs.map((proof) => (
                <div key={proof.id} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                        <div className="min-w-0 truncate">
                            <p className="truncate font-medium">{proof.original_name}</p>
                            <p className="text-xs text-muted-foreground">
                                {proof.payment?.period_start
                                    ? formatPeriod(proof.payment.period_start)
                                    : ''}
                            </p>
                        </div>
                    </div>
                    <span className="text-xs text-muted-foreground">{proof.mime_type}</span>
                </div>
            ))}
        </div>
    );
}
