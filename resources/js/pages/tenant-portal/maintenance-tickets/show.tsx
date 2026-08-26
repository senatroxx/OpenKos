import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { formatDate } from '@/lib/formatters';
import { t } from '@/lib/i18n';

type Ticket = {
    id: number;
    reference: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    created_at: string;
    property_name: string | null;
    unit_name: string | null;
    location: string | null;
    assignee_name: string | null;
    resolution_notes: string | null;
};

type Props = {
    ticket: Ticket;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

export default function ShowTicket({ ticket }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <Head title={`${t('Ticket')} ${ticket.reference}`} />

            <Link
                href="/portal/maintenance-tickets"
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                {t('Back to maintenance list')}
            </Link>

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">{ticket.title}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('Ticket')} {ticket.reference} · {t('Created')}{' '}
                        {formatDate(ticket.created_at)}
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-2">
                        <section className="space-y-2 rounded-lg border p-4">
                            <h2 className="text-sm font-semibold">
                                {t('Description')}
                            </h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {ticket.description ||
                                    t('No description provided.')}
                            </p>
                        </section>

                        {ticket.resolution_notes && (
                            <section className="space-y-2 rounded-lg border bg-accent/20 p-4">
                                <h2 className="text-sm font-semibold">
                                    {t('Resolution Notes')}
                                </h2>
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {ticket.resolution_notes}
                                </p>
                            </section>
                        )}
                    </div>

                    <div className="space-y-4">
                        <section className="divide-y rounded-lg border p-4 text-sm">
                            <Detail
                                label={t('Status')}
                                value={
                                    ticket.status.charAt(0).toUpperCase() +
                                    ticket.status.slice(1).replace('_', ' ')
                                }
                            />
                            <Detail
                                label={t('Priority')}
                                value={
                                    ticket.priority.charAt(0).toUpperCase() +
                                    ticket.priority.slice(1)
                                }
                            />
                            <Detail
                                label={t('Property')}
                                value={ticket.property_name || '—'}
                            />
                            {ticket.unit_name ? (
                                <Detail
                                    label={t('Location')}
                                    value={`${t('Unit')} ${ticket.unit_name}`}
                                />
                            ) : (
                                <Detail
                                    label={t('Location')}
                                    value={
                                        ticket.location
                                            ? `${t('Common Area')} (${ticket.location})`
                                            : t('Common Area')
                                    }
                                />
                            )}
                            <Detail
                                label={t('Assignee')}
                                value={ticket.assignee_name || t('Unassigned')}
                            />
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
