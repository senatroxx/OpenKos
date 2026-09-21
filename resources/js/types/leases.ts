import type { MoneyAggregate } from './dashboard';
import type { AvailableUnit, Lease, Property, Tenant } from './models';
import type { PaginatedData, TableMeta } from './table';

export type LeaseTenantOption = Pick<Tenant, 'id' | 'name'>;

export type PropertyLeasesPageProps = {
    property: Property;
    leases: PaginatedData<Lease>;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
    tenants: LeaseTenantOption[];
};

export type LeaseIndexPageProps = {
    leases: PaginatedData<Lease>;
    availableUnits: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    properties?: string;
    per_page?: number;
    payment_status?: string;
    table: TableMeta;
    stats?: {
        active_leases: number;
        collected_this_month: MoneyAggregate[];
        overdue_amount: MoneyAggregate[];
        pending_payment_verification: number;
    };
};

export type WholePropertyLeaseFormData = {
    tenant_ids: number[];
    property_rate_id: number | null;
    start_date: string;
    end_date: string;
    rent_amount: string;
    deposit_amount: string;
    rent_due_day: string;
    notes: string;
};

export type WholePropertyLeaseSheetProps = {
    property: Property;
    tenants: LeaseTenantOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};
