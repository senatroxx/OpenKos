import type { ReactNode } from 'react';
import type { Auth } from './auth';
import type {
    Amenity,
    ListingReadiness,
    ListingUnitType,
    Property,
    PropertyTypeOption,
    Region,
    Unit,
    UnitType,
    UnitTypeRate,
} from './models';
import type { PaginatedData, TableMeta } from './table';

export type ListingPageProps = {
    property: Property;
    amenities: Amenity[];
    readiness: ListingReadiness;
    regions: Region[];
    propertyTypes: PropertyTypeOption[];
};

export type UnitTypesPageProps = {
    property: Property;
    unitTypes: PaginatedData<UnitType>;
    amenities: Amenity[];
    rentalOptions: ListingUnitType[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export type UnitTypeRatesPageProps = {
    property: Property;
    unitType: UnitType & { rates: UnitTypeRate[] };
};

export type UnitTypeRatesFormData = {
    rates: UnitTypeRate[];
    updated_at: string | null;
};

export type UnitTypeWorkspaceListing = ListingUnitType | null;

export type UnitTypeWorkspaceProps = {
    property: Property;
    unitType: UnitType;
    listing: UnitTypeWorkspaceListing;
};

export type UnitTypeLayoutProps = {
    property: Property;
    unitType: UnitType;
    activeTab: string;
    actions?: ReactNode;
    children: ReactNode;
};

export type UnitTypeOverviewPageProps = UnitTypeWorkspaceProps;

export type UnitTypeListingPageProps = UnitTypeWorkspaceProps;

export type UnitTypeDetailValueProps = {
    label: string;
    value: string | number;
};

export type AuthPageProps = {
    auth: Auth;
};

export type AvailableUnitOption = {
    id: number;
    name: string;
    capacity: number;
    occupied_count?: number;
};

export type UnitWorkspaceProps = {
    property: Property;
    unit: Unit;
    availableUnits: AvailableUnitOption[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
};

export type UnitsPageProps = {
    property: Property;
    units: PaginatedData<Unit>;
    tenants: { id: number; name: string; phone: string }[];
    availableUnits: {
        id: number;
        name: string;
        property_id: number;
        capacity: number;
        occupied_count: number;
        property: { id: number; name: string; city: { name: string } | null } | null;
    }[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
    unitTypeWorkspace?: UnitType;
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export type UnitTypeDetailSheetProps = {
    unitType?: UnitType | null;
    option?: ListingUnitType | null;
    open: boolean;
    canManage: boolean;
    publishing: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onTogglePublication: () => void;
    onDelete: () => void;
};
