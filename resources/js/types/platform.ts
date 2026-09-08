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
    managed_id: string | null;
    declared_id: string | null;
    name: string;
    version: string | null;
    description: string;
    entry_class: string | null;
    core_version: string | null;
    php: string | null;
    dependencies: string[];
    provenance: 'marketplace' | 'manual' | null;
    marketplace_plugin_id: string | null;
    marketplace_version: string | null;
    artifact_sha256: string | null;
    installed_at: string | null;
    source: 'runtime' | 'composer' | 'explicit';
    status:
        | 'enabled'
        | 'disabled'
        | 'legacy'
        | 'incompatible'
        | 'broken'
        | 'conflict'
        | 'missing_package'
        | 'pending_recovery'
        | 'unrecoverable_recovery'
        | 'load_failed'
        | 'orphaned_state'
        | 'orphaned_runtime_artifact';
    enabled: boolean;
    error: string | null;
    can_enable: boolean;
    can_disable: boolean;
    can_remove: boolean;
    can_force_recovery: boolean;
    can_cleanup: boolean;
    cleanup_key: string | null;
};

export type MarketplaceVersion = {
    version: string;
    entry_class: string;
    compatibility: {
        openkos: string;
        platform: string;
        php: string;
    };
    published_at: string;
    artifact: {
        size: number;
        sha256: string;
    };
};

export type MarketplacePlugin = {
    id: string;
    name: string;
    summary: string | null;
    description: string | null;
    publisher: {
        name: string;
        url: string | null;
    } | null;
    repository_url: string | null;
    homepage_url: string | null;
    latest_version: MarketplaceVersion | null;
    latest_compatible_version: MarketplaceVersion | null;
    compatible: boolean;
    installed_version: string | null;
    installed_source:
        | PlatformPlugin['provenance']
        | PlatformPlugin['source']
        | null;
};

export type MarketplaceUpdate = {
    plugin_id: string;
    name: string;
    installed_version: string;
    available_version: MarketplaceVersion;
};

export type MarketplaceCatalog = {
    plugins: MarketplacePlugin[];
    updates: MarketplaceUpdate[];
    pagination: {
        current_page: number;
        total_page: number;
        total_records: number;
    };
    error: string | null;
};

export type Platform = {
    navigation: Record<string, PlatformNavigationItem[]>; // keyed by group, e.g. 'main'
    workspaces: Record<string, PlatformWorkspaceTab[]>; // keyed by workspace, e.g. 'property'
    settings: PlatformPage[];
    dashboard: PlatformPage[];
};
