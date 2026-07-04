import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import type { Lease, RentScheduleEntry } from '@/types';

const PAYMENT_METHOD_LABELS: Record<string, string> = {
    cash: 'Cash',
    transfer: 'Bank Transfer',
    ewallet: 'E-Wallet',
    other: 'Other',
};

function formatPeriod(periodStart: string): string {
    return new Date(periodStart).toLocaleDateString('id-ID', { year: 'numeric', month: 'long' });
}

export default function PaymentsTab({ lease }: { lease: Lease }) {
    const payments = lease.payments ?? [];
    const [schedule, setSchedule] = useState<RentScheduleEntry[] | null>(null);
    const loadingSchedule = schedule === null && lease.status === 'active';

    useEffect(() => {
        if (lease.status !== 'active') {
return;
}

        let cancelled = false;
        fetch(`/leases/${lease.id}/rent-schedule`)
            .then((r) => r.json())
            .then((d) => {
 if (!cancelled) {
setSchedule(d.schedule);
} 
});

        return () => {
 cancelled = true; 
};
    }, [lease.id, lease.status]);

    return (
        <>
            {payments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No payments recorded yet.</p>
            ) : (
                <div className="space-y-2">
                    {payments.map((payment) => (
                        <div key={payment.id} className="rounded-lg border p-3 text-sm">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">{formatPeriod(payment.period_start)}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {PAYMENT_METHOD_LABELS[payment.payment_method] ?? payment.payment_method}
                                        {payment.confirmed_by_user && ` · by ${payment.confirmed_by_user.name}`}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="font-medium tabular-nums">{formatPrice(payment.amount)}</p>
                                    {payment.status === 'confirmed' ? (
                                        <Badge className="bg-green-600 text-white text-[10px] px-1.5 py-0">
                                            {payment.verified_at ? 'Verified' : 'Paid'}
                                        </Badge>
                                    ) : payment.status === 'pending' ? (
                                        <Badge className="bg-amber-500 text-white text-[10px] px-1.5 py-0">
                                            Pending Review
                                        </Badge>
                                    ) : (
                                        <Badge className="bg-gray-400 text-white text-[10px] px-1.5 py-0">
                                            Cancelled
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            {payment.proofs && payment.proofs.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2 border-t pt-2">
                                    {payment.proofs.map((proof) => (
                                        <span key={proof.id} className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <FileText className="size-3" />
                                            {proof.original_name}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {lease.status === 'active' && (
                <div className="mt-6">
                    <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Rent Schedule
                    </h3>
                    {loadingSchedule ? (
                        <p className="text-sm text-muted-foreground">Loading schedule...</p>
                    ) : schedule && schedule.length > 0 ? (
                        <div className="space-y-2">
                            {schedule.map((entry, i) => {
                                const badge: Record<string, { label: string; className: string }> = {
                                    paid: { label: 'Paid', className: 'bg-green-600 text-white' },
                                    overdue: { label: 'Overdue', className: 'bg-red-600 text-white' },
                                    due: { label: 'Due', className: 'bg-yellow-500 text-white' },
                                    upcoming: { label: 'Upcoming', className: 'bg-gray-400 text-white' },
                                };
                                const s = badge[entry.status] ?? badge.upcoming;

                                return (
                                    <div key={i} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                                        <div>
                                            <p className="font-medium">{formatPeriod(entry.period_start)}</p>
                                            <p className="text-xs text-muted-foreground">Due {formatDate(entry.due_date)}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-medium tabular-nums">{formatPrice(entry.amount)}</p>
                                            <Badge className={`text-[10px] px-1.5 py-0 ${s.className}`}>{s.label}</Badge>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">No schedule data available.</p>
                    )}
                </div>
            )}
        </>
    );
}
