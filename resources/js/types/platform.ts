// Shapes of the `platform` shared Inertia prop, serialized from the
// OpenKOS platform registries (src/Platform/). See docs/platform.md.

export type PlatformNavigationItem = {
    title: string;
    href: string | null;
    icon: string | null; // lucide icon name, resolved client-side
    permission: string | null;
    children: PlatformNavigationItem[];
};

export type PlatformWorkspaceTab = {
    key: string;
    label: string;
    permission: string | null;
    meta: Record<string, unknown>;
};

export type PlatformPage = {
    key: string;
    title: string;
    href: string;
    permission: string | null;
    ownerOnly: boolean;
    group?: string | null;
};

export type PlatformPlugin = {
    id: string;
    managed_id: string;
    declared_id: string | null;
    name: string;
    version: string | null;
    description: string;
    entry_class: string | null;
    core_version: string | null;
    php: string | null;
    dependencies: string[];
    source: 'runtime' | 'composer' | 'explicit';
    status:
        | 'enabled'
        | 'disabled'
        | 'legacy'
        | 'incompatible'
        | 'broken'
        | 'conflict'
        | 'missing';
    enabled: boolean;
    error: string | null;
    can_enable: boolean;
    can_disable: boolean;
    can_remove: boolean;
};

export type Platform = {
    navigation: Record<string, PlatformNavigationItem[]>; // keyed by group, e.g. 'main'
    workspaces: Record<string, PlatformWorkspaceTab[]>; // keyed by workspace, e.g. 'property'
    settings: PlatformPage[];
    dashboard: PlatformPage[];
};
