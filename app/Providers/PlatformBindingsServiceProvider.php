<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Platform\ComposerPluginDiscovery;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Settings\SettingDefinition;
use OpenKOS\Platform\Settings\SettingsPage;

class PlatformBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComposerPluginDiscovery::class);
        $this->app->alias(ComposerPluginDiscovery::class, PluginDiscovery::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->registerPlatformSettingsPages();
        });
    }

    private function registerPlatformSettingsPages(): void
    {
        app(OpenKOSManager::class)->settings()
            ->registerPage(new SettingsPage('about', 'About', '/settings/about', ownerOnly: false, group: null, order: 50, routeName: 'settings.about.edit'))
            ->registerPage(new SettingsPage('profile', 'Profile', '/settings/profile', ownerOnly: false, group: 'Account', order: 100, routeName: 'profile.edit'))
            ->registerPage(new SettingsPage('security', 'Security', '/settings/security', ownerOnly: false, group: 'Account', order: 200, routeName: 'security.edit'))
            ->registerPage(new SettingsPage('general', 'General', '/settings/general', group: null, order: 0, routeName: 'settings.general.edit'))
            ->registerPage(new SettingsPage('payment-gateway', 'Payment Gateway', '/settings/payment-gateway', group: 'Integrations', order: 150, routeName: 'settings.payment-gateway.edit'))
            ->registerPage(new SettingsPage('reminders', 'Reminders', '/settings/reminders', group: 'Notifications', order: 100, routeName: 'settings.reminders.edit'))
            ->registerPage(new SettingsPage('mail', 'Mail', '/settings/mail', group: 'Integrations', order: 100, routeName: 'settings.mail.edit'))
            ->registerPage(new SettingsPage('plugins', 'Plugins', '/settings/plugins', order: 300, routeName: 'settings.plugins.index'))
            ->registerSetting(new SettingDefinition(
                key: PaymentGatewayManager::ACTIVE_KEY,
                label: 'Active payment gateway',
                default: null,
                rules: ['nullable', 'string'],
                page: 'payment-gateway',
            ))
            ->registerSetting(new SettingDefinition(
                key: PaymentGatewayManager::CONFIG_KEY,
                label: 'Payment gateway configuration',
                type: 'encrypted:array',
                default: [],
                rules: ['nullable', 'array'],
                page: 'payment-gateway',
            ));
    }
}
