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

    case UnitsView = 'units.view';
    case UnitsCreate = 'units.create';
    case UnitsUpdate = 'units.update';
    case UnitsDelete = 'units.delete';

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
    case LeasesRenew = 'leases.renew';

    case PaymentsCreate = 'payments.create';
    case PaymentsVerify = 'payments.verify';

    case RemindersSend = 'reminders.send';

    case FinancialsView = 'financials.view';
    case ReportsView = 'reports.view';

    case MaintenanceTicketsView = 'maintenance-tickets.view';
    case MaintenanceTicketsCreate = 'maintenance-tickets.create';
    case MaintenanceTicketsUpdate = 'maintenance-tickets.update';
    case MaintenanceTicketsDelete = 'maintenance-tickets.delete';
    case MaintenanceTicketsAssign = 'maintenance-tickets.assign';

    public function label(): string
    {
        $action = explode('.', $this->value)[1] ?? '';

        return match ($action) {
            'view' => 'View',
            'create' => 'Create',
            'verify' => 'Verify',
            'update' => 'Update',
            'delete' => 'Delete',
            'reset_password' => 'Reset Password',
            'resend_invitation' => 'Resend Invitation',
            'export' => 'Export',
            'move' => 'Move Unit',
            'move_out' => 'Move Out',
            'renew' => 'Renew Lease',
            'send' => 'Send',
            'clone' => 'Clone',
            'assign' => 'Assign',
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

            self::UnitsView => 'View units within properties.',
            self::UnitsCreate => 'Add new units to properties.',
            self::UnitsUpdate => 'Edit existing unit details.',
            self::UnitsDelete => 'Remove units from properties.',

            self::TenantsView => 'View the tenant list and details.',
            self::TenantsCreate => 'Register new tenants.',
            self::TenantsUpdate => 'Edit existing tenant information.',
            self::TenantsDelete => 'Archive tenant records.',
            self::TenantsExport => 'Export tenant data.',

            self::LeasesView => 'View lease agreements and history.',
            self::LeasesCreate => 'Create new leases and assign tenants to units.',
            self::LeasesUpdate => 'Edit existing lease terms.',
            self::LeasesDelete => 'Terminate lease agreements.',
            self::LeasesMove => 'Move a tenant to a different unit.',
            self::LeasesMoveOut => 'Process tenant move-out.',
            self::LeasesRenew => 'Renew a lease agreement.',

            self::PaymentsCreate => 'Record rent payments for leases.',
            self::PaymentsVerify => 'Verify or reject payment proof attachments.',
            self::RemindersSend => 'Send rent reminders to tenants.',
            self::FinancialsView => 'View financial reports and payment data.',
            self::ReportsView => 'Access generated reports.',

            self::MaintenanceTicketsView => 'View the maintenance ticket list.',
            self::MaintenanceTicketsCreate => 'Report new maintenance issues.',
            self::MaintenanceTicketsUpdate => 'Update existing maintenance ticket details.',
            self::MaintenanceTicketsDelete => 'Delete maintenance tickets.',
            self::MaintenanceTicketsAssign => 'Assign maintenance tickets to staff.',
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
