<?php

namespace App\Support;

use App\Enums\Permission;

class RecommendedRoles
{
    /**
     * @return array<int, array{name: string, label: string, description: string, color: string, permissions: string[]}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'Full operational access to properties, rooms, tenants, leases, and users.',
                'color' => '#2563eb',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::PropertiesView->value,
                    Permission::PropertiesCreate->value,
                    Permission::PropertiesUpdate->value,
                    Permission::PropertiesDelete->value,
                    Permission::RoomsView->value,
                    Permission::RoomsCreate->value,
                    Permission::RoomsUpdate->value,
                    Permission::RoomsDelete->value,
                    Permission::TenantsView->value,
                    Permission::TenantsCreate->value,
                    Permission::TenantsUpdate->value,
                    Permission::TenantsDelete->value,
                    Permission::LeasesView->value,
                    Permission::LeasesCreate->value,
                    Permission::LeasesUpdate->value,
                    Permission::LeasesDelete->value,
                    Permission::LeasesMove->value,
                    Permission::LeasesMoveOut->value,
                    Permission::FinancialsView->value,
                    Permission::ReportsView->value,
                    Permission::UsersView->value,
                    Permission::UsersUpdate->value,
                ],
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Day-to-day tenant and lease management.',
                'color' => '#059669',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::TenantsView->value,
                    Permission::TenantsUpdate->value,
                    Permission::LeasesView->value,
                ],
            ],
            [
                'name' => 'finance-staff',
                'label' => 'Finance Staff',
                'description' => 'View financial reports and tenant information.',
                'color' => '#d97706',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::FinancialsView->value,
                    Permission::TenantsView->value,
                    Permission::TenantsExport->value,
                ],
            ],
            [
                'name' => 'front-desk',
                'label' => 'Front Desk',
                'description' => 'Manage tenant check-ins, room viewings, and lease inquiries.',
                'color' => '#7c3aed',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::TenantsView->value,
                    Permission::TenantsCreate->value,
                    Permission::TenantsUpdate->value,
                    Permission::LeasesView->value,
                    Permission::RoomsView->value,
                ],
            ],
            [
                'name' => 'maintenance-staff',
                'label' => 'Maintenance Staff',
                'description' => 'Handle maintenance requests and view room assignments.',
                'color' => '#e11d48',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::RoomsView->value,
                ],
            ],
            [
                'name' => 'property-manager',
                'label' => 'Property Manager',
                'description' => 'Oversee multiple properties, manage leases and tenant relationships.',
                'color' => '#475569',
                'permissions' => [
                    Permission::DashboardView->value,
                    Permission::PropertiesView->value,
                    Permission::PropertiesUpdate->value,
                    Permission::RoomsView->value,
                    Permission::RoomsCreate->value,
                    Permission::RoomsUpdate->value,
                    Permission::TenantsView->value,
                    Permission::TenantsCreate->value,
                    Permission::TenantsUpdate->value,
                    Permission::LeasesView->value,
                    Permission::LeasesCreate->value,
                    Permission::LeasesUpdate->value,
                    Permission::LeasesMove->value,
                    Permission::LeasesMoveOut->value,
                ],
            ],
        ];
    }
}
