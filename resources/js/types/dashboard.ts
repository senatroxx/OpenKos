export type PropertyStats = {
    id: number;
    name: string;
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_percentage: number;
};

export type Finance = {
    revenue_this_month: number;
    monthly_potential: number;
    outstanding: number;
    collection_rate: number;
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
    days_overdue: number | null;
    urgency: 'overdue' | 'due_today' | 'due_tomorrow' | 'due_soon' | 'upcoming';
    status: string;
};

export type RecentPaymentEntry = {
    id: number;
    amount: string;
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
};
