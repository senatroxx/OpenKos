<?php

namespace App\Enums;

enum Permission: string
{
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case PropertiesManage = 'properties.manage';
    case RoomsManage = 'rooms.manage';
    case TenantsManage = 'tenants.manage';
    case FinancialsView = 'financials.view';
    case FinancialsManage = 'financials.manage';
    case ReportsView = 'reports.view';
    case DashboardView = 'dashboard.view';

    /**
     * @return Permission[]
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::Owner => self::all(),
            Role::Admin => [
                self::PropertiesManage,
                self::RoomsManage,
                self::TenantsManage,
                self::FinancialsView,
                self::ReportsView,
                self::DashboardView,
            ],
            Role::Staff => [
                self::TenantsManage,
                self::DashboardView,
            ],
        };
    }

    /**
     * @return Permission[]
     */
    public static function all(): array
    {
        return self::cases();
    }
}
