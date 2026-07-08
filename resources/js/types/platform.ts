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
    group?: string | null;
};

export type Platform = {
    navigation: Record<string, PlatformNavigationItem[]>; // keyed by group, e.g. 'main'
    workspaces: Record<string, PlatformWorkspaceTab[]>; // keyed by workspace, e.g. 'property'
    settings: PlatformPage[];
    dashboard: PlatformPage[];
};
