import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    Bell,
    CalendarClock,
    Check,
    ChevronDown,
    Clock,
    Download,
    MoreHorizontal,
    Square,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { SearchInput } from '@/components/data-table/search-input';
import { CurrencyAmountList } from '@/components/features/dashboard/currency-amount-list';
import InvoiceDetailSheet from '@/components/features/payments/invoice-detail-sheet';
import QueuePaymentSheet from '@/components/features/payments/queue-payment-sheet';
import { MetricCard } from '@/components/shared/metric-card';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPrice } from '@/lib/formatters';
import { rent as dashboardRent } from '@/routes/dashboard';
import type {
    NeedsAttentionInvoice,
    PaginatedData,
    RecentPaymentEntry,
    RecentReminderEntry,
    MoneyAggregate,
} from '@/types';

type TabCounts = {
    all: number;
    overdue: number;
    due_today: number;
    upcoming: number;
    pending_review: number;
    partial: number;
    paid: number;
};

type Progress = {
    processed: number;
    total: number;
    amount_collected: MoneyAggregate[];
    last_payment_at: string | null;
};

type PageProps = {
    entries: PaginatedData<NeedsAttentionInvoice>;
    sort?: string;
    search?: string;
    urgency?: string;
    properties?: string;
    per_page?: number;
    outstanding: { count: number; amounts: MoneyAggregate[] };
    tab_counts: TabCounts;
    progress: Progress;
    recent_payments: RecentPaymentEntry[];
    recent_reminders: RecentReminderEntry[];
};

const REMINDER_LABELS: Record<string, string> = {
    upcoming: 'Upcoming',
    due_today: 'Due Today',
    overdue: 'Overdue',
};

const TABS = [
    { key: '', label: 'All' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'due_today', label: 'Due Today' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'pending_review', label: 'Pending Review' },
    { key: 'partial', label: 'Partial' },
    { key: 'paid', label: 'Paid' },
] as const;

function urgencyLabel(
    urgency: string,
    daysOverdue: number | null,
    status: string,
): { text: string; color: string } {
    if (status === 'paid') {
        return {
            text: 'Paid',
            color: 'text-surface-green-foreground font-medium',
        };
    }

    if (status === 'partial') {
        return {
            text: 'Partial',
            color: 'text-surface-blue-foreground font-medium',
        };
    }

    if (urgency === 'overdue' && daysOverdue !== null) {
        const color =
            daysOverdue > 30
                ? 'text-surface-red-foreground font-medium'
                : 'text-surface-amber-foreground font-medium';

        return {
            text: `Overdue \u00B7 ${daysOverdue} day${daysOverdue !== 1 ? 's' : ''}`,
            color,
        };
    }

    if (urgency === 'due_today') {
        return {
            text: 'Due today',
            color: 'text-surface-amber-foreground font-medium',
        };
    }

    if (urgency === 'due_tomorrow') {
        return {
            text: 'Due tomorrow',
            color: 'text-surface-amber-foreground font-medium',
        };
    }

    return { text: 'Upcoming', color: 'text-muted-foreground' };
}

function pendingReviewLabel(count: number | undefined): string {
    if (!count || count < 1) {
        return 'Pending review';
    }

    return `Pending review · ${count}`;
}

function formatMoneyGroups(groups: MoneyAggregate[]): string {
    return (
        groups
            .map((group) => formatPrice(group.amount, group.currency))
            .join(' · ') || '—'
    );
}

export default function CollectionQueue({
    entries: data,
    sort: currentSort = 'due_date',
    search: currentSearch = '',
    urgency: currentUrgency = '',
    properties: currentProperties = '',
    per_page: currentPerPage = 25,
    outstanding,
    tab_counts: tabCounts,
    progress,
    recent_payments: recentPayments,
    recent_reminders: recentReminders,
}: PageProps) {
    const table = useTable({
        routeFn: () => dashboardRent(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            urgency: currentUrgency,
            properties: currentProperties,
        },
        defaults: {
            sort: 'due_date',
            per_page: '25',
        },
    });

    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === data.data.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(data.data.map((e) => e.id)));
        }
    };

    const clearSelection = () => setSelectedIds(new Set());

    const selectedCount = selectedIds.size;

    const [paymentSheetInvoice, setPaymentSheetInvoice] =
        useState<NeedsAttentionInvoice | null>(null);
    const [detailInvoice, setDetailInvoice] =
        useState<NeedsAttentionInvoice | null>(null);

    const openPaymentSheet = (entry: NeedsAttentionInvoice) => {
        setPaymentSheetInvoice(entry);
    };

    const openInvoiceDetail = (entry: NeedsAttentionInvoice) => {
        setDetailInvoice(entry);
    };

    const handleRecordPaymentFromDetail = (entry: NeedsAttentionInvoice) => {
        setDetailInvoice(null);
        setPaymentSheetInvoice(entry);
    };

    const applyTab = (tab: string) => {
        router.get(
            dashboardRent().url,
            {
                urgency: tab,
                page: '',
                search: currentSearch,
                sort: currentSort,
                per_page: String(currentPerPage),
                properties: currentProperties,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns: TableColumn<NeedsAttentionInvoice>[] = [
        {
            key: 'select',
            label: '',
            render: (entry) => {
                const checked = selectedIds.has(entry.id);

                return (
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            toggleSelect(entry.id);
                        }}
                    >
                        {checked ? (
                            <Check className="size-4 text-primary" />
                        ) : (
                            <Square className="size-4 text-muted-foreground" />
                        )}
                    </button>
                );
            },
        },
        {
            key: 'lease_reference',
            label: 'Lease',
            className: 'text-xs',
            render: (entry) =>
                entry.lease_reference ? (
                    <Link
                        href={`/leases/${entry.lease_id}`}
                        className="hover:underline"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {entry.lease_reference}
                    </Link>
                ) : (
                    <Link
                        href={`/leases/${entry.lease_id}`}
                        className="text-muted-foreground hover:underline"
                        onClick={(e) => e.stopPropagation()}
                    >
                        #{entry.lease_id}
                    </Link>
                ),
        },
        {
            key: 'tenant_name',
            label: 'Tenant',
            className: 'font-medium',
            render: (entry) =>
                entry.primary_tenant_id !== null ? (
                    <Link
                        href={`/tenants/${entry.primary_tenant_id}`}
                        className="hover:underline"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {entry.tenant_name}
                    </Link>
                ) : (
                    <span>{entry.tenant_name}</span>
                ),
        },
        {
            key: 'urgency',
            label: 'Status',
            render: (entry) => {
                if (currentUrgency === 'pending_review') {
                    return (
                        <span className="text-sm font-medium text-surface-purple-foreground">
                            {pendingReviewLabel(
                                entry.pending_payment_review_count,
                            )}
                        </span>
                    );
                }

                const { text, color } = urgencyLabel(
                    entry.urgency,
                    entry.days_overdue,
                    entry.status,
                );

                return <span className={`text-sm ${color}`}>{text}</span>;
            },
        },
        {
            key: 'total',
            label: 'Amount',
            sortable: true,
            className: 'tabular-nums font-medium',
            render: (entry) => formatPrice(entry.total, entry.currency),
        },
        {
            key: 'outstanding',
            label: 'Outstanding',
            className:
                'tabular-nums text-muted-foreground hidden sm:table-cell',
            render: (entry) => formatPrice(entry.outstanding, entry.currency),
        },
        {
            key: 'due_date',
            label: 'Due',
            sortable: true,
            className: 'tabular-nums text-muted-foreground',
            render: (entry) => formatDate(entry.due_date),
        },
        {
            key: 'actions',
            label: '',
            render: (entry) => {
                const isPaid = entry.status === 'paid';

                return (
                    <div
                        className="flex items-center gap-1"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm">
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    disabled={isPaid}
                                    onClick={() => openPaymentSheet(entry)}
                                >
                                    <Banknote className="mr-2 size-4" />
                                    Record Payment
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/leases/${entry.lease_id}/invoices/${entry.id}`}
                                    >
                                        <ArrowUpRight className="mr-2 size-4" />
                                        View Invoice
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`/leases/${entry.lease_id}`}>
                                        <Bell className="mr-2 size-4" />
                                        View Lease
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    const onRowClick = (entry: NeedsAttentionInvoice) => {
        openInvoiceDetail(entry);
    };

    const progressPercent =
        progress.total > 0
            ? Math.round((progress.processed / progress.total) * 100)
            : 0;
    const lastPaymentDate = formatDate(progress.last_payment_at);

    return (
        <>
            <Head title="Collection Queue" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                {/* Header cards */}
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold tracking-tight">
                        Collection Queue
                    </h1>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        label="Overdue"
                        value={tabCounts.overdue}
                        variant="red"
                        emphasis="attention"
                        icon={AlertTriangle}
                    />
                    <MetricCard
                        label="Due Today"
                        value={tabCounts.due_today}
                        variant="amber"
                        emphasis="subtle"
                        icon={CalendarClock}
                    />
                    <MetricCard
                        label="Upcoming"
                        value={tabCounts.upcoming}
                        variant="blue"
                        emphasis="subtle"
                        icon={Bell}
                    />
                    <MetricCard
                        label="Pending Review"
                        value={tabCounts.pending_review}
                        variant="purple"
                        emphasis="subtle"
                        icon={Bell}
                    />
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <MetricCard
                        label="Outstanding Balance"
                        value={
                            <CurrencyAmountList
                                groups={outstanding.amounts}
                                amountClassName="text-surface-amber-foreground"
                            />
                        }
                        subtext={`${outstanding.count} invoice${outstanding.count !== 1 ? 's' : ''} unpaid`}
                        variant="amber"
                        emphasis="subtle"
                        icon={Banknote}
                    />
                    <MetricCard
                        label="Last Payment Recorded"
                        value={lastPaymentDate}
                        variant="neutral"
                        icon={Clock}
                    />
                </div>

                {/* Progress */}
                {progress.total > 0 && (
                    <div className="rounded-xl border border-border/70 bg-card p-4 shadow-xs">
                        <div className="mb-2 flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <div className="flex size-6 items-center justify-center rounded-md bg-surface-green-icon/80 text-surface-green-foreground">
                                    <TrendingUp className="size-3.5" />
                                </div>
                                <span className="font-semibold text-foreground">
                                    Collection Progress
                                </span>
                            </div>
                            <span className="text-xs font-medium text-muted-foreground tabular-nums">
                                {progress.processed} / {progress.total}{' '}
                                processed
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-surface-green-foreground transition-all duration-300"
                                style={{ width: `${progressPercent}%` }}
                            />
                        </div>
                        <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span className="font-medium tabular-nums">
                                {formatMoneyGroups(progress.amount_collected)}{' '}
                                collected
                            </span>
                            <span className="font-bold text-foreground tabular-nums">
                                {progressPercent}%
                            </span>
                        </div>
                    </div>
                )}

                {/* Selection toolbar */}
                <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-4 py-2 text-sm">
                    {selectedCount > 0 ? (
                        <>
                            <span className="font-medium tabular-nums">
                                {selectedCount} selected
                            </span>
                            <Button size="sm" variant="outline" disabled>
                                <Banknote className="mr-2 size-4" />
                                Record Payment
                            </Button>
                            <Button size="sm" variant="outline" disabled>
                                <Bell className="mr-2 size-4" />
                                Send Reminder
                            </Button>
                            <Button size="sm" variant="outline" disabled>
                                <Download className="mr-2 size-4" />
                                Export
                            </Button>
                            <button
                                type="button"
                                onClick={clearSelection}
                                className="ml-auto text-muted-foreground hover:text-foreground"
                            >
                                Clear selection
                            </button>
                        </>
                    ) : (
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={toggleSelectAll}
                                className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <Square className="size-4" />
                                Select all
                            </button>
                            <span className="text-muted-foreground">
                                to enable bulk actions.
                            </span>
                        </div>
                    )}
                </div>

                {/* Tab pills */}
                <div className="flex items-center gap-1 overflow-x-auto pb-1">
                    {TABS.map((tab) => {
                        const count =
                            tab.key === ''
                                ? tabCounts.all
                                : (tabCounts[tab.key as keyof TabCounts] ?? 0);
                        const active =
                            currentUrgency === tab.key ||
                            (tab.key === '' && currentUrgency === '');

                        return (
                            <button
                                key={tab.key || 'all'}
                                type="button"
                                onClick={() =>
                                    applyTab(tab.key === '' ? '' : tab.key)
                                }
                                className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                }`}
                            >
                                {tab.label}
                                <span
                                    className={`inline-flex size-5 items-center justify-center rounded-full text-xs ${
                                        active
                                            ? 'bg-primary-foreground/20'
                                            : 'bg-muted-foreground/15'
                                    }`}
                                >
                                    {count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Search */}
                <div className="flex items-center gap-1">
                    <SearchInput
                        value={table.searchValue}
                        onChange={table.onSearchChange}
                        onClear={table.clearSearch}
                        placeholder="Search tenant, invoice, unit, property..."
                    />
                </div>

                {/* Queue */}
                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={onRowClick}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun="invoices"
                    empty={{
                        message:
                            currentUrgency === 'paid'
                                ? 'No paid invoices this period.'
                                : currentUrgency === 'partial'
                                  ? 'No partially paid invoices.'
                                  : currentUrgency === 'upcoming'
                                    ? 'No upcoming invoices.'
                                    : 'All caught up. Nothing needs attention.',
                        createLabel:
                            currentUrgency === '' ? 'View Paid' : undefined,
                        onCreate:
                            currentUrgency === ''
                                ? () => applyTab('paid')
                                : undefined,
                    }}
                />

                {/* Activity */}
                {recentPayments.length > 0 && (
                    <Collapsible defaultOpen={false}>
                        <CollapsibleTrigger className="group flex w-full items-center gap-2 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                            <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                            Recent Payments ({recentPayments.length})
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            {recentPayments.length > 0 && (
                                <div className="divide-y rounded-lg border">
                                    {recentPayments.map((payment, index) => (
                                        <div
                                            key={
                                                payment.id ?? `payment-${index}`
                                            }
                                            className="flex items-center justify-between px-4 py-3 text-sm"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">
                                                    {payment.tenant_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {payment.invoice_reference}
                                                    {' · '}
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="ml-3 text-right">
                                                <p className="font-medium text-green-600 tabular-nums">
                                                    +
                                                    {formatPrice(
                                                        payment.amount,
                                                        payment.currency,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {PAYMENT_METHOD_LABELS[
                                                        payment.payment_method
                                                    ] ?? payment.payment_method}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CollapsibleContent>
                    </Collapsible>
                )}

                {recentReminders.length > 0 && (
                    <Collapsible defaultOpen={false}>
                        <CollapsibleTrigger className="group flex w-full items-center gap-2 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                            <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                            Reminder Activity ({recentReminders.length})
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            {recentReminders.length > 0 && (
                                <div className="divide-y rounded-lg border">
                                    {recentReminders.map((reminder, index) => (
                                        <div
                                            key={
                                                reminder.id ??
                                                `reminder-${index}`
                                            }
                                            className="flex items-center justify-between px-4 py-3 text-sm"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">
                                                    {reminder.tenant_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {REMINDER_LABELS[
                                                        reminder.reminder_type
                                                    ] ?? reminder.reminder_type}
                                                    {' · '}
                                                    {reminder.channel}
                                                    {reminder.sent_at &&
                                                        ` · ${formatDate(reminder.sent_at)}`}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CollapsibleContent>
                    </Collapsible>
                )}
            </div>

            <InvoiceDetailSheet
                key={
                    detailInvoice
                        ? `detail-invoice-${detailInvoice.id}`
                        : 'detail-invoice-idle'
                }
                invoice={detailInvoice}
                open={detailInvoice !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailInvoice(null);
                    }
                }}
                onRecordPayment={handleRecordPaymentFromDetail}
            />

            <QueuePaymentSheet
                key={
                    paymentSheetInvoice
                        ? `payment-sheet-${paymentSheetInvoice.id}`
                        : 'payment-sheet-idle'
                }
                invoice={paymentSheetInvoice}
                open={paymentSheetInvoice !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPaymentSheetInvoice(null);
                    }
                }}
            />
        </>
    );
}

CollectionQueue.layout = {
    breadcrumbs: [
        {
            title: 'Billing',
            href: dashboardRent(),
        },
        {
            title: 'Collection',
            href: dashboardRent(),
        },
    ],
};
