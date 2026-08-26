import { Link } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { AttentionData } from '@/types';

function getGreeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

export function OperationalBriefingCard({
    attention,
}: {
    attention: AttentionData;
}) {
    const greeting = getGreeting();
    const totalAttention =
        attention.overdue_invoices.count +
        attention.due_today +
        attention.open_maintenance +
        attention.leases_ending_soon +
        attention.pending_payment_verification;

    return (
        <section className="mb-8">
            <div className="rounded-xl border border-border bg-card p-5 shadow-xs transition-all">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-2">
                        <div className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            <Sparkles className="size-3 text-primary" />
                            <span>{t('Operational Briefing')}</span>
                        </div>
                        <h2 className="text-lg font-bold tracking-tight text-foreground sm:text-xl">
                            {t(greeting)}.
                        </h2>
                        <p className="text-xs font-medium text-muted-foreground sm:text-sm">
                            {totalAttention > 0 ? (
                                <>
                                    {t('You have')}{' '}
                                    <span className="font-bold text-foreground tabular-nums">
                                        {totalAttention}{' '}
                                        {t(
                                            totalAttention === 1
                                                ? 'operational item'
                                                : 'operational items',
                                        )}
                                    </span>{' '}
                                    {t('requiring attention today.')}
                                </>
                            ) : (
                                t(
                                    'All operational items are currently up to date.',
                                )
                            )}
                        </p>

                        {totalAttention > 0 && (
                            <ul className="grid gap-2 pt-1 text-xs font-medium text-foreground sm:grid-cols-2">
                                {attention.pending_payment_verification > 0 && (
                                    <li className="flex items-center gap-2">
                                        <span className="size-1.5 shrink-0 rounded-full bg-surface-purple-foreground" />
                                        <span>
                                            <strong className="font-bold tabular-nums">
                                                {
                                                    attention.pending_payment_verification
                                                }
                                            </strong>{' '}
                                            {t(
                                                attention.pending_payment_verification ===
                                                    1
                                                    ? 'payment awaiting verification'
                                                    : 'payments awaiting verification',
                                            )}
                                        </span>
                                    </li>
                                )}
                                {attention.overdue_invoices.count > 0 && (
                                    <li className="flex items-center gap-2">
                                        <span className="size-1.5 shrink-0 rounded-full bg-surface-red-foreground" />
                                        <span>
                                            <strong className="font-bold text-surface-red-foreground tabular-nums">
                                                {
                                                    attention.overdue_invoices
                                                        .count
                                                }
                                            </strong>{' '}
                                            {t(
                                                attention.overdue_invoices
                                                    .count === 1
                                                    ? 'overdue invoice'
                                                    : 'overdue invoices',
                                            )}
                                        </span>
                                    </li>
                                )}
                                {attention.open_maintenance > 0 && (
                                    <li className="flex items-center gap-2">
                                        <span className="size-1.5 shrink-0 rounded-full bg-surface-amber-foreground" />
                                        <span>
                                            <strong className="font-bold tabular-nums">
                                                {attention.open_maintenance}
                                            </strong>{' '}
                                            {t(
                                                attention.open_maintenance === 1
                                                    ? 'open maintenance ticket'
                                                    : 'open maintenance tickets',
                                            )}
                                        </span>
                                    </li>
                                )}
                                {attention.leases_ending_soon > 0 && (
                                    <li className="flex items-center gap-2">
                                        <span className="size-1.5 shrink-0 rounded-full bg-surface-blue-foreground" />
                                        <span>
                                            <strong className="font-bold tabular-nums">
                                                {attention.leases_ending_soon}
                                            </strong>{' '}
                                            {t(
                                                attention.leases_ending_soon ===
                                                    1
                                                    ? 'lease ending soon'
                                                    : 'leases ending soon',
                                            )}
                                        </span>
                                    </li>
                                )}
                            </ul>
                        )}
                    </div>

                    <div className="flex shrink-0 flex-col items-start gap-2.5 sm:flex-row md:flex-col md:items-end">
                        <Button
                            variant="default"
                            size="sm"
                            asChild
                            className="cursor-pointer gap-2 shadow-xs"
                        >
                            <Link href="/dashboard/rent">
                                {t('Review Attention Queue')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Link
                            href="/dashboard/rent"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary transition-colors hover:underline"
                        >
                            {t('View Billing Collection')} →
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
