import { Head } from '@inertiajs/react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { StatusBadge } from '@/components/shared/status-badge';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatPrice } from '@/lib/formatters';
import { t } from '@/lib/i18n';
import type { MaintenanceTicket } from '@/types';

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value ?? '—'}</p>
        </div>
    );
}

export default function MaintenanceTicketWorkspace({
    ticket,
}: {
    ticket: MaintenanceTicket;
}) {
    return (
        <EntityWorkspaceLayout
            title={ticket.title}
            subtitle={ticket.reference ?? `#${ticket.id}`}
            backRoute="/maintenance-tickets"
            backLabel={t('All tickets')}
        >
            <Head title={`${ticket.title} — ${t('Maintenance')}`} />

            <WorkspaceTabs
                workspace="maintenance-ticket"
                activeTab="overview"
                hrefParams={{ id: ticket.id }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/maintenance-tickets/${ticket.id}`,
                    },
                ]}
            />

            <div className="space-y-6">
                <div className="flex gap-2">
                    <StatusBadge domain="maintenance" value={ticket.status} />
                    <Badge variant="outline">{ticket.priority}</Badge>
                </div>

                <div className="grid grid-cols-2 gap-4 rounded-lg border p-4 md:grid-cols-4">
                    <Field
                        label={t('Property')}
                        value={ticket.property?.name}
                    />
                    <Field
                        label={t('Unit')}
                        value={ticket.unit?.name ?? ticket.location}
                    />
                    <Field
                        label={t('Assigned to')}
                        value={ticket.assignee?.name}
                    />
                    <Field
                        label={t('Reported by')}
                        value={ticket.creator?.name}
                    />
                    <Field label={t('Cost')} value={formatPrice(ticket.cost)} />
                    <Field
                        label={t('Created')}
                        value={formatDate(ticket.created_at)}
                    />
                    <Field
                        label={t('Resolved')}
                        value={
                            ticket.resolved_at
                                ? formatDate(ticket.resolved_at)
                                : '—'
                        }
                    />
                </div>

                {ticket.description && (
                    <div>
                        <p className="mb-2 text-xs text-muted-foreground">
                            {t('Description')}
                        </p>
                        <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                            {ticket.description}
                        </p>
                    </div>
                )}

                {ticket.resolution_notes && (
                    <div>
                        <p className="mb-2 text-xs text-muted-foreground">
                            {t('Resolution notes')}
                        </p>
                        <p className="rounded-lg border bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                            {ticket.resolution_notes}
                        </p>
                    </div>
                )}
            </div>
        </EntityWorkspaceLayout>
    );
}
