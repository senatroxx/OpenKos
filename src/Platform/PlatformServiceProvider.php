<?php

namespace OpenKOS\Platform;

use Illuminate\Support\ServiceProvider;
use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DashboardRegistry::class);
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(WorkspaceRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(NotificationRegistry::class);
        $this->app->singleton(PaymentRegistry::class);
        $this->app->singleton(OpenKOSManager::class);
    }

    public function boot(): void
    {
        $manager = $this->app->make(OpenKOSManager::class);

        /** @var array<int, Plugin> $plugins */
        $plugins = array_map(
            fn (string $class) => $this->app->make($class),
            config('platform.plugins', []),
        );

        // Two passes: boot() may rely on every plugin having registered.
        foreach ($plugins as $plugin) {
            $plugin->register($manager);
        }

        foreach ($plugins as $plugin) {
            $plugin->boot($manager);
        }
    }
}
