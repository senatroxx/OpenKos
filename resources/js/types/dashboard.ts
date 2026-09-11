import type { InvoiceLineItem, Payment } from './models';

export type PropertyStats = {
    id: number;
    name: string;
    slug: string;
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_percentage: number;
};

export type Finance = {
    revenue_this_month: MoneyAggregate[];
    monthly_potential: MoneyAggregate[];
    outstanding: MoneyAggregate[];
    collection_rate: Array<{ currency: string; rate: number }>;
    expenses: {
        this_month: MoneyAggregate[];
        last_month: MoneyAggregate[];
        change_vs_last_month: MoneyAggregate[];
    };
};

export type MoneyAggregate = {
    currency: string;
    amount: string;
};

export type RateAggregate = {
    currency: string;
    rate: string;
};

export type FinancialTrendPoint = {
    month: string;
    label: string;
    revenue: MoneyAggregate[];
    expenses: MoneyAggregate[];
    noi: MoneyAggregate[];
};

export type FinancialCashFlowPoint = {
    month: string;
    label: string;
    collected: MoneyAggregate[];
    expenses: MoneyAggregate[];
};

export type FinancialPropertyPerformance = {
    id: number;
    name: string;
    revenue: MoneyAggregate[];
    expenses: MoneyAggregate[];
    noi: MoneyAggregate[];
    operating_margin: RateAggregate[];
    billed: MoneyAggregate[];
    collected: MoneyAggregate[];
    outstanding: MoneyAggregate[];
    collection_rate: RateAggregate[];
    occupancy: {
        total_units: number;
        occupied_units: number;
        occupancy_percentage: number;
    };
};

export type FinancialDashboardData = {
    period: 'current_month' | 'ytd';
    period_start: string;
    period_end: string;
    overview: {
        revenue: MoneyAggregate[];
        expenses: MoneyAggregate[];
        noi: MoneyAggregate[];
        operating_margin: RateAggregate[];
    };
    collections: {
        billed: MoneyAggregate[];
        collected: MoneyAggregate[];
        outstanding: MoneyAggregate[];
        collection_rate: RateAggregate[];
    };
    cash_flow: FinancialCashFlowPoint[];
    trends: FinancialTrendPoint[];
    property_performance: FinancialPropertyPerformance[];
    occupancy: {
        total_units: number;
        occupied_units: number;
        occupancy_percentage: number;
        properties: Array<{
            id: number;
            name: string;
            total_units: number;
            occupied_units: number;
            occupancy_percentage: number;
        }>;
    };
    expense_breakdown: Array<{
        category_id: number;
        category_label: string;
        amounts: MoneyAggregate[];
    }>;
    upcoming_receivables: Array<{
        month: string;
        label: string;
        receivable: MoneyAggregate[];
    }>;
};

export type Stats = {
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_percentage: number;
    properties: PropertyStats[];
};

export type RentDashboardEntry = {
    id: number;
    tenant_name: string;
    unit_name: string;
    property_name: string;
    rent_due_day: number;
    days_overdue: number | null;
    rent_amount: string;
    currency: string;
    rent_status: 'paid' | 'overdue' | 'due_today' | 'due_soon';
};

export type BillingStats = {
    overdue: { count: number; amount: number };
    due_today: number;
    due_soon: number;
    outstanding_balance: number;
    collection_rate: number;
};

export type NeedsAttentionInvoice = {
    id: number;
    lease_id: number;
    lease_reference: string | null;
    primary_tenant_id: number | null;
    tenant_name: string;
    unit_name: string;
    property_name: string;
    reference: string;
    period_start: string;
    period_end: string;
    due_date: string;
    total: string;
    amount_paid: string;
    outstanding: string;
    currency: string;
    days_overdue: number | null;
    urgency: 'overdue' | 'due_today' | 'due_tomorrow' | 'due_soon' | 'upcoming';
    status: string;
    pending_payment_review_count?: number;
    line_items?: InvoiceLineItem[];
    payments?: Payment[];
};

export type RecentPaymentEntry = {
    id: number;
    amount: string;
    currency: string;
    payment_date: string;
    payment_method: string;
    status: string;
    tenant_name: string;
    invoice_id: number;
    invoice_reference: string;
    lease_id: number | null;
};

export type RecentReminderEntry = {
    id: number;
    lease_id: number;
    tenant_name: string;
    reminder_type: string;
    channel: string;
    scheduled_for: string;
    sent_at: string | null;
    overdue_days: number | null;
};

export type CollectionProgress = {
    paid_this_month: number;
    outstanding_this_month: number;
    monthly_potential: number;
    collection_rate: number;
};

export type AttentionItem = {
    label: string;
    count: number;
    amount?: number;
    href: string;
};

export type RecentActivityEntry = {
    id: number;
    description: string;
    created_at: string;
    subject_type: string | null;
    subject_id?: number | null;
    actor_name?: string | null;
    action_url?: string | null;
};

export type AttentionData = {
    overdue_invoices: { count: number; amounts: MoneyAggregate[] };
    due_today: number;
    open_maintenance: number;
    leases_ending_soon: number;
    pending_payment_verification: number;
};
