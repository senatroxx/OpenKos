import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import RoleForm from '@/components/role-form';
import roles from '@/routes/roles';

type PermissionEntry = { value: string; label: string; description: string };
type PermissionGroup = Record<string, PermissionEntry[]>;

type RoleData = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    color: string | null;
    is_system: boolean;
    is_active: boolean;
    permissions: string[];
};

export default function Edit({
    role,
    permissionGroups,
}: {
    role: RoleData;
    permissionGroups: PermissionGroup;
}) {
    return (
        <>
            <Head title={`Edit ${role.label}`} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title={`Edit ${role.label}`} description="Update role settings and permissions" />

                {role.is_system && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        This is a system role. Some settings cannot be modified.
                    </div>
                )}

                <RoleForm
                    role={role}
                    permissionGroups={permissionGroups}
                    action={roles.update.url(role)}
                    method="put"
                />
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        { title: 'Roles & Permissions', href: roles.index() },
        { title: 'Edit Role', href: '' },
    ],
};
