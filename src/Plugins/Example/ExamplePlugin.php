<?php

namespace OpenKOS\Plugins\Example;

use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Settings\SettingsPage;
use OpenKOS\Platform\Workspace\WorkspaceTab;

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

        // Stateful workspace (lease/tenant/room): the tab body renders the
        // client-side region 'workspace-tab-example' (resources/js/plugins/example).
        $platform->lease()->registerTab(new WorkspaceTab(
            key: 'example',
            label: 'Example',
        ));

        // URL-routed workspace (property): tabs link out via meta.href,
        // {id} is replaced with the property id client-side.
        $platform->property()->registerTab(new WorkspaceTab(
            key: 'example',
            label: 'Example',
            meta: ['href' => '/properties/{id}'],
        ));

        $platform->settings()->registerPage(new SettingsPage(
            key: 'example',
            title: 'Example',
            href: '/settings/example',
        ));
    }
}
