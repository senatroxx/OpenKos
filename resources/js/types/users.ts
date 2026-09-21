import type { RoleOption } from './models';
import type { PaginatedData, TableMeta } from './table';

export type UserPropertyOption = { id: number; name: string };
export type UserRole = { name: string; label: string };

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: UserRole[];
    role: string | null;
    properties: UserPropertyOption[];
    is_active: boolean;
    status: 'active' | 'invited' | 'disabled';
    invited_at: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
};

export type UsersPageProps = {
    users: PaginatedData<ManagedUser>;
    properties: UserPropertyOption[];
    roles: RoleOption[];
    search?: string;
    role?: string;
    status?: string;
    sort?: string;
    per_page?: number;
    table: TableMeta;
};
