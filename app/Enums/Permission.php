<?php

namespace App\Enums;

enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case UsersResetPassword = 'users.reset_password';
    case UsersResendInvitation = 'users.resend_invitation';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case RolesClone = 'roles.clone';

    case PropertiesView = 'properties.view';
    case PropertiesCreate = 'properties.create';
    case PropertiesUpdate = 'properties.update';
    case PropertiesDelete = 'properties.delete';

    case RoomsView = 'rooms.view';
    case RoomsCreate = 'rooms.create';
    case RoomsUpdate = 'rooms.update';
    case RoomsDelete = 'rooms.delete';

    case TenantsView = 'tenants.view';
    case TenantsCreate = 'tenants.create';
    case TenantsUpdate = 'tenants.update';
    case TenantsDelete = 'tenants.delete';
    case TenantsExport = 'tenants.export';

    case LeasesView = 'leases.view';
    case LeasesCreate = 'leases.create';
    case LeasesUpdate = 'leases.update';
    case LeasesDelete = 'leases.delete';
    case LeasesMove = 'leases.move';
    case LeasesMoveOut = 'leases.move_out';

    case FinancialsView = 'financials.view';
    case ReportsView = 'reports.view';

    public function label(): string
    {
        $action = explode('.', $this->value)[1] ?? '';

        return match ($action) {
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'reset_password' => 'Reset Password',
            'resend_invitation' => 'Resend Invitation',
            'export' => 'Export',
            'move' => 'Move Room',
            'move_out' => 'Move Out',
            'clone' => 'Clone',
            default => $action,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DashboardView => 'Access the dashboard overview page.',

            self::UsersView => 'View the staff user list.',
            self::UsersCreate => 'Invite new staff users.',
            self::UsersUpdate => 'Edit existing staff user details and roles.',
            self::UsersDelete => 'Disable staff user access.',
            self::UsersResetPassword => 'Send password reset links to staff users.',
            self::UsersResendInvitation => 'Resend invitation emails to invited staff.',

            self::RolesView => 'View the roles and permissions list.',
            self::RolesCreate => 'Create new custom roles.',
            self::RolesUpdate => 'Edit existing role settings and permissions.',
            self::RolesDelete => 'Delete custom roles.',
            self::RolesClone => 'Duplicate an existing role with its permissions.',

            self::PropertiesView => 'View the property list and details.',
            self::PropertiesCreate => 'Add new properties.',
            self::PropertiesUpdate => 'Edit existing property information.',
            self::PropertiesDelete => 'Archive properties.',

            self::RoomsView => 'View rooms within properties.',
            self::RoomsCreate => 'Add new rooms to properties.',
            self::RoomsUpdate => 'Edit existing room details.',
            self::RoomsDelete => 'Remove rooms from properties.',

            self::TenantsView => 'View the tenant list and details.',
            self::TenantsCreate => 'Register new tenants.',
            self::TenantsUpdate => 'Edit existing tenant information.',
            self::TenantsDelete => 'Archive tenant records.',
            self::TenantsExport => 'Export tenant data.',

            self::LeasesView => 'View lease agreements and history.',
            self::LeasesCreate => 'Create new leases and assign tenants to rooms.',
            self::LeasesUpdate => 'Edit existing lease terms.',
            self::LeasesDelete => 'Terminate lease agreements.',
            self::LeasesMove => 'Move a tenant to a different room.',
            self::LeasesMoveOut => 'Process tenant move-out.',

            self::FinancialsView => 'View financial reports and payment data.',
            self::ReportsView => 'Access generated reports.',
        };
    }

    /**
     * @return array<string, array<int, array{value: string, label: string, description: string}>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $group = explode('.', $permission->value)[0];

            $groups[$group][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
            ];
        }

        return $groups;
    }

    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::Owner => self::all(),
        };
    }

    public static function all(): array
    {
        return self::cases();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
