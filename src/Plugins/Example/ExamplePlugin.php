<?php

namespace OpenKOS\Plugins\Example;

use OpenKOS\Platform\Dashboard\DashboardPage;
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;
use OpenKOS\Platform\Settings\SettingsPage;

/**
 * Reference plugin exercising the platform boot path. Demonstrates each
 * consumed registry: a sidebar nav item, a Dashboard sub-page, a settings
 * page, and a workspace-header badge (client side).
 */
class ExamplePlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'openkos/example',
            name: 'Example Plugin',
            version: '1.0.0',
            description: 'Reference plugin demonstrating every extension point.',
            coreVersion: '^0.1',
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        $platform->navigation()->registerItem(new NavigationItem(
            title: 'Example Plugin',
            href: '/example',
            icon: 'puzzle',
        ));

        $platform->dashboard()->registerPage(new DashboardPage(
            key: 'example',
            title: 'Example',
            href: '/dashboard/example',
        ));

        // Workspace tabs are URL-routed: meta.href is required, and
        // {placeholders} ({id}, and {propertyId} on rooms) are resolved
        // client-side. A real plugin would register its own route + page:
        //
        // $platform->lease()->registerTab(new WorkspaceTab(
        //     key: 'insurance',
        //     label: 'Insurance',
        //     meta: ['href' => '/leases/{id}/insurance'],
        // ));

        $platform->settings()->registerPage(new SettingsPage(
            key: 'example',
            title: 'Example',
            href: '/settings/example',
        ));
    }
}
