export type Tenant = {
    id: number;
    user_id: number | null;
    name: string;
    phone: string | null;
    id_card_number: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    is_active: boolean;
    deleted_at?: string | null;
    active_leases_count?: number;
    documents?: TenantDocument[];
    user?: {
        id: number;
        email: string;
        email_verified_at: string | null;
        last_login_at: string | null;
        is_active: boolean;
        invited_at: string | null;
    } | null;
};

export type TenantInfo = {
    id: number;
    name: string;
    phone: string | null;
    pivot?: { is_primary: boolean };
};

export type TenantDocument = {
    id: number;
    type: string;
    original_name: string;
    size: number;
    mime_type: string;
    created_at: string;
    download_url: string;
};

export type Region = {
    id: number;
    name: string;
    cities: { id: number; name: string }[];
};

// A user-managed property classification (App\Models\PropertyType). The slug
// is stored on properties.type; the label is the editable display name.
export type PropertyTypeOption = {
    id?: number;
    slug: string;
    label: string;
    is_active?: boolean;
    sort_order?: number;
    properties_count?: number;
};

export type Property = {
    id: number;
    name: string;
    type?: string; // property_types.slug
    type_label?: string; // resolved label (appended by the model)
    slug: string; // route key
    address?: string | null;
    region_id?: number | null;
    city_id?: number | null;
    postal_code?: string | null;
    phone?: string | null;
    is_active?: boolean;
    city?: string | { id: number; name: string } | null;
    region?: { id: number; name: string } | null;
    units_count?: number;
    occupied_units_count?: number;
    tenants_count?: number;
};

export type UnitRate = {
    id?: number;
    billing_interval: number;
    billing_unit: 'day' | 'week' | 'month' | 'year';
    amount: string;
};

export type Unit = {
    id: number;
    name: string;
    slug: string; // route key (unique per property)
    floor: string | null;
    description: string | null;
    size_sqm: string | null;
    capacity: number;
    occupied_count?: number;
    property_id?: number;
    property?: Property | null;
    status: string;
    notes: string | null;
    active_leases?: number;
    leases?: LeaseInfo[];
    active_rates?: UnitRate[];
    tenants?: TenantInfo[];
    deleted_at?: string | null;
};

export type UnitWithProperty = Unit & {
    property_id: number;
    property: Property | null;
};

export type AvailableUnit = {
    id: number;
    name: string;
    property_id: number;
    capacity: number;
    occupied_count: number;
    active_rates?: UnitRate[];
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

export type TenantLease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    unit: Unit | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

export type LeaseInfo = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    billing_interval: number;
    billing_unit: string;
    billing_strategy?: string;
    monthly_equivalent: string;
    billing_label: string;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    notes: string | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    payments?: Payment[];
};

export type Lease = {
    id: number;
    reference: string | null;
    payment_status?: string | null;
    pending_payment_review_count?: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_interval: number;
    billing_unit: string;
    billing_strategy?: string;
    monthly_equivalent: string;
    billing_label: string;
    deposit_amount: string;
    deposit_paid_at: string | null;
    deposit_refund_amount: string | null;
    deposit_refunded_at: string | null;
    rent_due_day: number;
    status: string;
    termination_date: string | null;
    termination_reason: string | null;
    notes: string | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    unit: UnitWithProperty | null;
    payments?: Payment[];
    unit_histories?: {
        id: number;
        from_unit: { id: number; name: string } | null;
        to_unit: { id: number; name: string } | null;
        transferred_by: {
            id: number;
            name: string;
            roles?: { name: string; label?: string }[];
        } | null;
        reason: string | null;
        notes: string | null;
        effective_date: string;
    }[];
};

export type TenantLeaseContextLease = {
    id: number;
    start_date: string;
    end_date: string | null;
    status: string;
    unit_name: string | null;
    property_name: string | null;
};

export type TenantLeaseContext = {
    selected: TenantLeaseContextLease | null;
    leases: TenantLeaseContextLease[];
};

export type LeaseData = {
    id: number;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    unit: { id: number; name: string } | null;
};

export type PaymentProof = {
    id: number;
    payment_id: number;
    path: string;
    original_name: string;
    mime_type: string;
    created_at: string;
};

export type InvoiceLineItem = {
    id: number;
    invoice_id: number;
    type: string;
    description: string;
    amount: string;
};

export type PaymentAllocation = {
    id: number;
    payment_id: number;
    invoice_id: number;
    amount: string;
};

export type Invoice = {
    id: number;
    lease_id: number;
    reference: string | null;
    period_start: string;
    period_end: string;
    due_date: string;
    status: string;
    display_status?: string;
    total: string;
    amount_paid: string;
    outstanding?: string;
    payable_amount?: string;
    line_items?: InvoiceLineItem[];
    payments?: Payment[];
    allocations?: PaymentAllocation[];
};

export type Payment = {
    id: number;
    invoice_id: number;
    invoice?: Pick<
        Invoice,
        'id' | 'reference' | 'period_start' | 'period_end' | 'status'
    > | null;
    amount: string;
    payment_date: string;
    payment_method: string;
    reference: string | null;
    notes: string | null;
    status: string;
    confirmed_by: number | null;
    confirmed_by_user?: { id: number; name: string } | null;
    recorded_by: number | null;
    recorded_by_user?: { id: number; name: string } | null;
    verified_by: number | null;
    verified_by_user?: { id: number; name: string } | null;
    verified_at: string | null;
    proofs: PaymentProof[];
};

export type RentScheduleEntry = {
    id: number;
    period_start: string;
    period_end: string;
    due_date: string;
    amount: string;
    amount_paid: string;
    outstanding: string;
    status: 'paid' | 'partial' | 'overdue' | 'due' | 'upcoming' | 'cancelled';
};

export type MaintenanceTicket = {
    id: number;
    reference: string | null;
    property_id: number;
    unit_id: number | null;
    location: string | null;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    assigned_to: number | null;
    created_by: number | null;
    cost: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    created_at: string;
    updated_at: string;
    property?: { id: number; name: string } | null;
    unit?: { id: number; name: string } | null;
    assignee?: {
        id: number;
        name: string;
        roles?: { name: string; label?: string }[];
    } | null;
    creator?: {
        id: number;
        name: string;
        roles?: { name: string; label?: string }[];
    } | null;
    maintenance_transfer_to?: string | null;
};

export type ProofRow = {
    id: number;
    payment_id: number;
    original_name: string;
    mime_type: string;
    created_at: string;
    payment: {
        id: number;
        invoice_id: number;
        amount: string;
        status: string;
        invoice: { id: number; period_start: string } | null;
    } | null;
};

export type WorkspaceLease = {
    id: number;
    reference: string | null;
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_strategy?: string;
    status: string;
};

export type WorkspaceTenant = Omit<Tenant, 'documents'> & {
    active_leases_count?: number;
};

export type WorkspaceUser = {
    id: number;
    name: string;
    email: string;
    roles: { name: string; label: string }[];
    role: string | null;
    properties: { id: number; name: string }[];
    is_active: boolean;
    status: 'active' | 'invited' | 'disabled';
    invited_at: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
};

export type ManagedProperty = {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label?: string;
    address: string | null;
    region_id: number | null;
    city_id: number | null;
    postal_code: string | null;
    phone: string | null;
    is_active: boolean;
    units_count: number;
    occupied_units_count: number;
    tenants_count: number;
    city?: { id: number; name: string } | null;
};

export type WorkspaceProperty = { id: number; slug: string; name: string };

export type WorkspaceUnit = {
    id: number;
    name: string;
    slug: string;
    floor: string | null;
    status?: string;
    capacity?: number;
    occupied_count?: number;
};

export type PropertyRef = { id: number; name: string };

export type RoleOption = { value: string; label: string };
