<?php

namespace OpenKOS\Plugins\Example;

use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Settings\SettingsPage;

/**
 * Reference plugin exercising the platform boot path. The frontend does not
 * consume registries yet, so these registrations have no visible effect.
 */
class ExamplePlugin extends Plugin
{
    public function register(OpenKOSManager $platform): void
    {
        $platform->navigation()->registerItem(new NavigationItem(
            title: 'Example Plugin',
            href: '/example',
            icon: 'puzzle',
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
