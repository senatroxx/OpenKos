export type Tenant = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
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

export type RoomWithProperty = Room & {
    property_id: number;
    property: Property | null;
};

export type AvailableRoom = {
    id: number;
    name: string;
    property_id: number;
    capacity: number;
    occupied_count: number;
    active_rates?: RoomRate[];
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
    room: Room | null;
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
    room: RoomWithProperty | null;
    payments?: Payment[];
    room_histories?: {
        id: number;
        from_room: { id: number; name: string } | null;
        to_room: { id: number; name: string } | null;
        transferred_by: { id: number; name: string; roles?: { name: string; label?: string }[] } | null;
        reason: string | null;
        notes: string | null;
        effective_date: string;
    }[];
};

export type LeaseData = {
    id: number;
    tenants: TenantInfo[];
    primary_tenant: TenantInfo | null;
    room: { id: number; name: string } | null;
};

export type PaymentProof = {
    id: number;
    payment_id: number;
    path: string;
    original_name: string;
    mime_type: string;
    created_at: string;
};

export type Payment = {
    id: number;
    lease_id: number;
    amount: string;
    payment_date: string;
    period_start: string;
    period_end: string;
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
    period_start: string;
    period_end: string;
    due_date: string;
    amount: string;
    status: 'paid' | 'overdue' | 'due' | 'upcoming';
};

export type MaintenanceTicket = {
    id: number;
    reference: string | null;
    property_id: number;
    room_id: number | null;
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
    room?: { id: number; name: string } | null;
    assignee?: { id: number; name: string; roles?: { name: string; label?: string }[] } | null;
    creator?: { id: number; name: string; roles?: { name: string; label?: string }[] } | null;
    maintenance_transfer_to?: string | null;
};
