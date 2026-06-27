export type Tenant = {
    id: number;
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

export type Property = {
    id: number;
    name: string;
    slug?: string;
    address?: string | null;
    region_id?: number | null;
    city_id?: number | null;
    postal_code?: string | null;
    phone?: string | null;
    is_active?: boolean;
    city?: string | { id: number; name: string } | null;
    region?: { id: number; name: string } | null;
    rooms_count?: number;
    occupied_rooms_count?: number;
    tenants_count?: number;
};

export type RoomRate = {
    id?: number;
    billing_interval: number;
    billing_unit: 'day' | 'week' | 'month' | 'year';
    amount: string;
};

export type Room = {
    id: number;
    name: string;
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
    active_rates?: RoomRate[];
    tenants?: TenantInfo[];
};

export type RoomInfo = {
    id: number;
    name: string;
    property_id: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

export type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    capacity: number;
    occupied_count: number;
    property: {
        id: number;
        name: string;
        city: { name: string } | null;
    } | null;
};

export type TenantLease = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    room: Room | null;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
};

export type LeaseInfo = {
    id: number;
    start_date: string;
    end_date: string | null;
    rent_amount: string;
    billing_interval: number;
    billing_unit: string;
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
    start_date: string;
    end_date: string | null;
    rent_amount: string | null;
    billing_interval: number;
    billing_unit: string;
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
    room: RoomInfo | null;
    payments?: Payment[];
};

export type LeaseData = {
    id: number;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    room: RoomInfo | null;
};

export type Payment = {
    id: number;
    lease_id: number;
    amount: string;
    payment_date: string;
    period_start: string;
    period_end: string;
    payment_method: string;
    notes: string | null;
    status: string;
    confirmed_by: number | null;
    confirmed_by_user?: { id: number; name: string } | null;
    recorded_by: number | null;
    recorded_by_user?: { id: number; name: string } | null;
    proof_path: string | null;
};

export type RentScheduleEntry = {
    period_start: string;
    period_end: string;
    due_date: string;
    amount: string;
    status: 'paid' | 'overdue' | 'due' | 'upcoming';
};

export type PermissionEntry = {
    value: string;
    label: string;
    description: string;
};
