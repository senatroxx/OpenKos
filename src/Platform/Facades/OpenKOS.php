<?php

namespace OpenKOS\Platform\Facades;

use Illuminate\Support\Facades\Facade;
use OpenKOS\Platform\OpenKOSManager;

/**
 * Import this class directly (use OpenKOS\Platform\Facades\OpenKOS) —
 * a global alias is intentionally not registered because a root-level
 * `OpenKOS` alias would collide with the OpenKOS\ namespace.
 *
 * @method static \OpenKOS\Platform\Dashboard\DashboardRegistry dashboard()
 * @method static \OpenKOS\Platform\Navigation\NavigationRegistry navigation()
 * @method static \OpenKOS\Platform\Workspace\WorkspaceRegistry workspaces()
 * @method static \OpenKOS\Platform\Workspace\Workspace workspace(string $name)
 * @method static \OpenKOS\Platform\Workspace\Workspace property()
 * @method static \OpenKOS\Platform\Workspace\Workspace lease()
 * @method static \OpenKOS\Platform\Workspace\Workspace tenant()
 * @method static \OpenKOS\Platform\Settings\SettingsRegistry settings()
 * @method static \OpenKOS\Platform\Notification\NotificationRegistry notifications()
 * @method static \OpenKOS\Platform\Payment\PaymentRegistry payments()
 *
 * @see OpenKOSManager
 */
class OpenKOS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OpenKOSManager::class;
    }
}
