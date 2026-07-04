<?php

namespace OpenKOS\Platform;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLoader;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;
use ReflectionClass;

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

        // Validate core-version compatibility + dependencies, order by dependency.
        $plugins = (new PluginLoader)->prepare($plugins, config('platform.version', '0.1.0'));

        // A plugin's own routes/migrations load by convention (no boilerplate).
        foreach ($plugins as $plugin) {
            $this->loadPluginResources($plugin);
        }

        // Two passes: boot() may rely on every plugin having registered.
        foreach ($plugins as $plugin) {
            $plugin->register($manager);
        }

        foreach ($plugins as $plugin) {
            $plugin->boot($manager);
        }

        foreach ($plugins as $plugin) {
            $this->registerListeners($plugin);
        }
    }

    /**
     * Convention-over-configuration: load `routes/web.php` and
     * `database/migrations/` from the plugin's own directory if present.
     */
    private function loadPluginResources(Plugin $plugin): void
    {
        $dir = dirname((new ReflectionClass($plugin))->getFileName());

        if (is_file($routes = $dir.'/routes/web.php')) {
            $this->loadRoutesFrom($routes);
        }

        if (is_dir($migrations = $dir.'/database/migrations')) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function registerListeners(Plugin $plugin): void
    {
        foreach ($plugin->listens() as $event => $listeners) {
            foreach ((array) $listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
