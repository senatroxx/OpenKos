import { Head } from '@inertiajs/react';
import { EntityWorkspaceLayout } from '@/components/shared/entity-workspace-layout';
import { WorkspaceTabs } from '@/components/shared/workspace-tabs';
import { Badge } from '@/components/ui/badge';

type WorkspaceUser = {
    id: number;
    name: string;
    email: string;
    roles: { name: string; label: string }[];
    properties: { id: number; name: string }[];
    is_active: boolean;
    status: string;
    invited_at: string | null;
    email_verified_at: string | null;
    last_login_at: string | null;
};

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value ?? '—'}</p>
        </div>
    );
}

export default function UserWorkspace({ user }: { user: WorkspaceUser }) {
    return (
        <EntityWorkspaceLayout
            title={user.name}
            subtitle={user.email}
            backRoute="/users"
            backLabel="All users"
        >
            <Head title={`${user.name} — User`} />

            <WorkspaceTabs
                workspace="user"
                activeTab="overview"
                hrefParams={{ id: user.id }}
                tabs={[
                    {
                        key: 'overview',
                        label: 'Overview',
                        href: `/users/${user.id}`,
                    },
                ]}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">{user.status}</Badge>
                    {user.roles.map((role) => (
                        <Badge key={role.name} variant="secondary">
                            {role.label}
                        </Badge>
                    ))}
                </div>

                <div className="grid grid-cols-2 gap-4 rounded-lg border p-4 md:grid-cols-3">
                    <Field
                        label="Last login"
                        value={
                            user.last_login_at
                                ? new Date(user.last_login_at).toLocaleString()
                                : 'Never'
                        }
                    />
                    <Field
                        label="Email verified"
                        value={
                            user.email_verified_at
                                ? new Date(
                                      user.email_verified_at,
                                  ).toLocaleDateString()
                                : 'No'
                        }
                    />
                    <Field
                        label="Invited"
                        value={
                            user.invited_at
                                ? new Date(user.invited_at).toLocaleDateString()
                                : '—'
                        }
                    />
                </div>

                <div>
                    <p className="mb-2 text-xs text-muted-foreground">
                        Assigned properties
                    </p>
                    {user.properties.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No properties assigned.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {user.properties.map((property) => (
                                <Badge key={property.id} variant="outline">
                                    {property.name}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </EntityWorkspaceLayout>
    );
}
