import { Head } from '@inertiajs/react';
import { RoleForm } from '@/components/features';
import { Heading } from '@/components/shared';
import { t } from '@/lib/i18n';
import roles from '@/routes/roles';
import type { PermissionGroup } from '@/types';

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
            <Head title={t('Create Role')} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title={t('Create Role')}
                    description={t(
                        'Define a new custom role and its permissions',
                    )}
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
