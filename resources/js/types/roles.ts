export type PermissionEntry = {
    value: string;
    label: string;
    description: string;
};

export type PermissionGroup = Record<string, PermissionEntry[]>;

export type RoleData = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    color: string | null;
    guard_name: string;
    is_system: boolean;
    is_active: boolean;
    users_count: number;
    permissions_count: number;
    permissions: string[];
    created_at: string | null;
};

export type RoleFormData = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    color: string | null;
    is_system: boolean;
    is_active: boolean;
    permissions: string[];
};
