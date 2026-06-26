import { Head } from '@inertiajs/react';
import { RoleForm } from '@/components/features';
import { Heading } from '@/components/shared';
import roles from '@/routes/roles';

type PermissionEntry = { value: string; label: string; description: string };
type PermissionGroup = Record<string, PermissionEntry[]>;

export default function Create({
    permissionGroups,
    recommendations,
}: {
    permissionGroups: PermissionGroup;
    recommendations: {
        name: string;
        label: string;
        description: string;
        color: string;
        permissions: string[];
    }[];
}) {
    return (
        <>
            <Head title="Create Role" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Create Role"
                    description="Define a new custom role and its permissions"
                />

                <RoleForm
                    permissionGroups={permissionGroups}
                    recommendations={recommendations}
                    action={roles.store.url()}
                    method="post"
                />
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs: [
        { title: 'Roles & Permissions', href: roles.index() },
        { title: 'Create Role', href: roles.create() },
    ],
};
