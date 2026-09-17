import type {
    Amenity,
    ListingReadiness,
    ListingUnitType,
    Property,
    PropertyTypeOption,
    Region,
    Unit,
    UnitType,
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
    assignment?: string;
    per_page?: number;
    table: TableMeta;
};

export type AvailableUnitOption = {
    id: number;
    name: string;
    capacity: number;
    occupied_count?: number;
};

export type UnitIndexPageProps = {
    property: Property;
    units: PaginatedData<Unit>;
    tenants: { id: number; name: string; phone: string }[];
    availableUnits: AvailableUnitOption[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
    sort?: string;
    search?: string;
    status?: string;
    assignment?: string;
    per_page?: number;
    table: TableMeta;
};

export type BulkAssignUnitTypeSheetProps = {
    property: Property;
    units: Unit[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export type BulkAssignUnitTypeFormData = {
    unit_ids: number[];
    unit_type_id: string;
};

export type UnitWorkspaceProps = {
    property: Property;
    unit: Unit;
    availableUnits: AvailableUnitOption[];
    unitTypes: Pick<UnitType, 'id' | 'property_id' | 'name' | 'is_active'>[];
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
