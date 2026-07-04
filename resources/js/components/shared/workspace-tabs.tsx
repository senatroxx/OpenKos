import { Link } from '@inertiajs/react';
import { PluginRegion } from '@/components/shared/plugin-region';
import { usePlatformTabs } from '@/lib/platform';

export type WorkspaceTabDef = {
    key: string;
    label: string;
    href: string;
};

/**
 * URL-routed tab strip shared by all entity workspaces. Platform tabs for
 * the given workspace are appended; they must provide meta.href, with
 * {placeholders} resolved from hrefParams (e.g. '/tenants/{id}/foo').
 */
export function WorkspaceTabs({
    workspace,
    tabs,
    activeTab,
    hrefParams = {},
}: {
    workspace: string;
    tabs: WorkspaceTabDef[];
    activeTab: string;
    hrefParams?: Record<string, string | number>;
}) {
    const platformTabs = usePlatformTabs(workspace)
        .filter((t) => typeof t.meta.href === 'string')
        .map((t) => ({
            key: t.key,
            label: t.label,
            href: Object.entries(hrefParams).reduce(
                (href, [param, value]) =>
                    href.replaceAll(`{${param}}`, String(value)),
                t.meta.href as string,
            ),
        }));

    return (
        <>
            <PluginRegion name="workspace-tabs-before" />

            <div className="mb-6 border-b">
                <nav className="-mb-px flex gap-6">
                    {[...tabs, ...platformTabs].map((t) => (
                        <Link
                            key={t.key}
                            href={t.href}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                activeTab === t.key
                                    ? 'border-b-2 border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </Link>
                    ))}
                </nav>
            </div>

            <PluginRegion name="workspace-tabs-after" />
        </>
    );
}
