<?php

namespace Tests;

use App\Services\Platform\RuntimePluginDiscovery;
use App\Services\Platform\RuntimePluginStore;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Permission\PermissionRegistry;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Platform\Settings\SettingsManager;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;

abstract class TestCase extends BaseTestCase
{
    protected function bootPlatformWithIsolatedRegistries(): void
    {
        foreach ([
            DashboardRegistry::class,
            NavigationRegistry::class,
            WorkspaceRegistry::class,
            SettingsRegistry::class,
            SettingsManager::class,
            NotificationRegistry::class,
            PaymentRegistry::class,
            PermissionRegistry::class,
            OpenKOSManager::class,
            RuntimePluginDiscovery::class,
            RuntimePluginStore::class,
        ] as $singleton) {
            app()->forgetInstance($singleton);
        }

        (new PlatformServiceProvider(app()))->boot();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
