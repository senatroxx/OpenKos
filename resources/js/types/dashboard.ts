export type PropertyStats = {
    id: number;
    name: string;
    total_rooms: number;
    occupied_rooms: number;
    available_rooms: number;
    maintenance_rooms: number;
    unavailable_rooms: number;
    occupancy_percentage: number;
};

export type Finance = {
    revenue_this_month: number;
    monthly_potential: number;
    outstanding: number;
    collection_rate: number;
};

export type Stats = {
    total_rooms: number;
    occupied_rooms: number;
    available_rooms: number;
    maintenance_rooms: number;
    unavailable_rooms: number;
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
