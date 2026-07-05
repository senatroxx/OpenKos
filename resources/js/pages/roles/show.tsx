import { Head, Link } from '@inertiajs/react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type WorkspaceRole = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    color: string | null;
    is_system: boolean;
    is_active: boolean;
    users_count: number;
    permissions: string[];
    created_at: string | null;
};

export default function RoleWorkspace({ role }: { role: WorkspaceRole }) {
    return (
        <EntityWorkspaceLayout
            title={role.label}
            subtitle={role.description ?? undefined}
            backRoute="/roles"
            backLabel="All roles"
            actions={
                <Button size="sm" asChild>
                    <Link href={`/roles/${role.id}/edit`}>Edit role</Link>
                </Button>
            }
        >
            <Head title={`${role.label} — Role`} />

            <WorkspaceTabs
                workspace="role"
                activeTab="overview"
                hrefParams={{ id: role.id }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/roles/${role.id}`,
                    },
                ]}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">
                        {role.is_active ? 'Active' : 'Disabled'}
                    </Badge>
                    {role.is_system && (
                        <Badge variant="secondary">System role</Badge>
                    )}
                    <Badge variant="outline">
                        {role.users_count}{' '}
                        {role.users_count === 1 ? 'user' : 'users'}
                    </Badge>
                </div>

                <div>
                    <p className="mb-2 text-xs text-muted-foreground">
                        Permissions ({role.permissions.length})
                    </p>
                    {role.permissions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No permissions granted.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {role.permissions.map((permission) => (
                                <Badge
                                    key={permission}
                                    variant="outline"
                                    className="font-mono text-xs"
                                >
                                    {permission}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </EntityWorkspaceLayout>
    );
}
