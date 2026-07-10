import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store as createRole, update as updateRole } from '@/actions/App/Http/Controllers/RoleController';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { PermissionGroup } from '@/types';

const PERMISSION_LABELS: Record<string, string> = {
    dashboard: 'Dashboard',
    users: 'Users',
    roles: 'Roles',
    properties: 'Properties',
    units: 'Units',
    tenants: 'Tenants',
    leases: 'Leases',
    financials: 'Financials',
    reports: 'Reports',
};

const COLOR_SWATCHES = [
    '#2563eb',
    '#059669',
    '#d97706',
    '#e11d48',
    '#7c3aed',
    '#475569',
    '#0891b2',
    '#be185d',
    '#65a30d',
    '#ea580c',
];

type RoleFormProps = {
    role?: {
        id: number;
        name: string;
        label: string;
        description: string | null;
        color: string | null;
        is_system: boolean;
        is_active: boolean;
        permissions: string[];
    } | null;
    permissionGroups: PermissionGroup;
    recommendations?: {
        name: string;
        label: string;
        description: string;
        color: string;
        permissions: string[];
    }[];
};

export default function RoleForm({
    role,
    permissionGroups,
    recommendations,
}: RoleFormProps) {
    const isEdit = Boolean(role);
    const isSystem = role?.is_system ?? false;
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>(
        () => role?.permissions ?? [],
    );
    const [labelValue, setLabelValue] = useState(role?.label ?? '');
    const [nameValue, setNameValue] = useState(role?.name ?? '');
    const [descriptionValue, setDescriptionValue] = useState(
        role?.description ?? '',
    );
    const [colorValue, setColorValue] = useState(
        role?.color ?? COLOR_SWATCHES[0],
    );
    const [isActiveValue, setIsActiveValue] = useState(role?.is_active ?? true);

    function applyRecommendation(rec: {
        name: string;
        label: string;
        description: string;
        color: string;
        permissions: string[];
    }) {
        setNameValue(rec.name);
        setLabelValue(rec.label);
        setDescriptionValue(rec.description);
        setColorValue(rec.color);
        setSelectedPermissions(rec.permissions);
    }

    const form = useForm({
        name: role?.name ?? '',
        label: role?.label ?? '',
        description: role?.description ?? '',
        color: role?.color ?? '',
        is_active: role?.is_active ?? true,
        permissions: role?.permissions ?? [],
    });

    const { processing, errors } = form;

    function togglePermission(permission: string, checked: boolean) {
        setSelectedPermissions((current) =>
            checked
                ? [...current, permission]
                : current.filter((p) => p !== permission),
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(() => ({
            name: isEdit ? role?.name : nameValue,
            label: labelValue,
            description: descriptionValue,
            color: colorValue,
            is_active: isActiveValue,
            permissions: selectedPermissions,
        }));
        form.submit(isEdit ? updateRole(role!.id) : createRole(), { onSuccess: () => {} });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-8">
                    {!isEdit &&
                        recommendations &&
                        recommendations.length > 0 && (
                            <section>
                                <h3 className="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Quick start templates
                                </h3>
                                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                                    {recommendations.map((rec) => (
                                        <button
                                            key={rec.name}
                                            type="button"
                                            onClick={() =>
                                                applyRecommendation(rec)
                                            }
                                            className="rounded-lg border border-input bg-transparent p-3 text-left text-sm transition-colors hover:bg-accent"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="inline-block size-3 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            rec.color,
                                                    }}
                                                />
                                                <span className="font-medium">
                                                    {rec.label}
                                                </span>
                                            </div>
                                            <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                {rec.description}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </section>
                        )}

                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                {isEdit ? 'Identifier' : 'Role identifier'}
                            </Label>
                            {isEdit ? (
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={role?.name ?? ''}
                                    disabled={isSystem}
                                    required
                                />
                            ) : (
                                <Input
                                    id="name"
                                    name="name"
                                    value={nameValue}
                                    onChange={(e) => {
                                        setNameValue(e.target.value);

                                        if (!labelValue) {
                                            setLabelValue(
                                                e.target.value
                                                    .replace(/[-_]/g, ' ')
                                                    .replace(/\b\w/g, (c) =>
                                                        c.toUpperCase(),
                                                    ),
                                            );
                                        }
                                    }}
                                    placeholder="e.g. finance-staff"
                                    required
                                />
                            )}
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="label">Display name</Label>
                            <Input
                                id="label"
                                name="label"
                                value={labelValue}
                                onChange={(e) => setLabelValue(e.target.value)}
                                placeholder="e.g. Finance Staff"
                                disabled={isSystem}
                                required
                            />
                            <InputError message={errors.label} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                name="description"
                                value={descriptionValue}
                                onChange={(e) =>
                                    setDescriptionValue(e.target.value)
                                }
                                placeholder="What this role can do..."
                                disabled={isSystem}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Color</Label>
                            <div className="flex flex-wrap gap-2">
                                {COLOR_SWATCHES.map((swatch) => (
                                    <button
                                        key={swatch}
                                        type="button"
                                        onClick={() => setColorValue(swatch)}
                                        className={`inline-block size-7 rounded-full border-2 transition-all ${
                                            colorValue === swatch
                                                ? 'scale-110 border-foreground'
                                                : 'border-transparent'
                                        }`}
                                        style={{ backgroundColor: swatch }}
                                        disabled={isSystem}
                                    />
                                ))}
                            </div>
                            <InputError message={errors.color} />
                        </div>

                        {isEdit && !isSystem && (
                            <div className="flex items-center gap-3 self-end pb-1">
                                <Switch
                                    checked={isActiveValue}
                                    onCheckedChange={setIsActiveValue}
                                />
                                <Label className="cursor-pointer">Active</Label>
                            </div>
                        )}

                        {isEdit && isSystem && null}
                    </section>

                    <div className="flex items-center justify-end">
                        <Button disabled={processing}>
                            {isEdit ? 'Save Changes' : 'Create Role'}
                        </Button>
                    </div>

                    <section>
                        <h3 className="mb-4 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Permissions
                        </h3>
                        {isSystem && (
                            <p className="mb-4 text-sm text-muted-foreground">
                                System roles have all permissions and cannot be
                                modified.
                            </p>
                        )}
                        <div className="space-y-6 rounded-lg border p-4">
                            {Object.entries(permissionGroups).map(
                                ([group, permissions]) => (
                                    <div key={group}>
                                        <h4 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            {PERMISSION_LABELS[group] ?? group}
                                        </h4>
                                        <div className="space-y-0">
                                            {permissions.map((perm) => (
                                                <div
                                                    key={perm.value}
                                                    className="flex items-center gap-3 border-b border-border py-2.5 text-sm last:border-0"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <span className="font-medium">
                                                            {perm.label}
                                                        </span>
                                                        <p className="text-xs text-muted-foreground">
                                                            {perm.description}
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        checked={selectedPermissions.includes(
                                                            perm.value,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            togglePermission(
                                                                perm.value,
                                                                checked,
                                                            )
                                                        }
                                                        disabled={isSystem}
                                                        className="shrink-0"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                        <InputError message={errors.permissions} />
                    </section>
                </div>
            </form>
    );
}
