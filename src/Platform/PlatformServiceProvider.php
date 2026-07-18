<?php

namespace OpenKOS\Platform;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Platform\Console\SyncPluginPermissionsCommand;
use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Permission\PermissionRegistry;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLoader;
use OpenKOS\Platform\Settings\SettingsManager;
use OpenKOS\Platform\Settings\SettingsPage;
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
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(NotificationRegistry::class);
        $this->app->singleton(PaymentRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(OpenKOSManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncPluginPermissionsCommand::class]);
        }

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

        $this->registerCoreSettingsPages($manager);
    }

    private function registerCoreSettingsPages(OpenKOSManager $manager): void
    {
        $manager->settings()
            ->registerPage(new SettingsPage('profile', 'Profile', '/settings/profile', group: 'Account', order: 100, routeName: 'profile.edit'))
            ->registerPage(new SettingsPage('security', 'Security', '/settings/security', group: 'Account', order: 200, routeName: 'security.edit'))
            ->registerPage(new SettingsPage('general', 'General', '/settings/general', group: 'Preferences', order: 100, routeName: 'settings.general.edit'))
            ->registerPage(new SettingsPage('reminders', 'Reminders', '/settings/reminders', group: 'Preferences', order: 300, routeName: 'settings.reminders.edit'))
            ->registerPage(new SettingsPage('property-types', 'Property Types', '/settings/property-types', group: 'Property', order: 100, routeName: 'settings.property-types.index'))
            ->registerPage(new SettingsPage('mail', 'Mail', '/settings/mail', group: 'Integrations', order: 100, routeName: 'settings.mail.edit'));
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
