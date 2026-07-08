<?php

namespace OpenKOS\Platform;

use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Permission\PermissionRegistry;
use OpenKOS\Platform\Settings\SettingsManager;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\Workspace;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;

/**
 * Central entry point of the OpenKOS platform. Bound as a singleton;
 * plugins receive it in register()/boot(), app code uses the OpenKOS facade.
 */
class OpenKOSManager
{
    public function __construct(
        private readonly DashboardRegistry $dashboard,
        private readonly NavigationRegistry $navigation,
        private readonly WorkspaceRegistry $workspaces,
        private readonly SettingsRegistry $settings,
        private readonly SettingsManager $settingsManager,
        private readonly NotificationRegistry $notifications,
        private readonly PaymentRegistry $payments,
        private readonly PermissionRegistry $permissions,
    ) {}

    public function dashboard(): DashboardRegistry
    {
        return $this->dashboard;
    }

    public function navigation(): NavigationRegistry
    {
        return $this->navigation;
    }

    public function workspaces(): WorkspaceRegistry
    {
        return $this->workspaces;
    }

    public function workspace(string $name): Workspace
    {
        return $this->workspaces->for($name);
    }

    public function property(): Workspace
    {
        return $this->workspace('property');
    }

    public function lease(): Workspace
    {
        return $this->workspace('lease');
    }

    public function tenant(): Workspace
    {
        return $this->workspace('tenant');
    }

    public function settings(): SettingsRegistry
    {
        return $this->settings;
    }

    public function settingsManager(): SettingsManager
    {
        return $this->settingsManager;
    }

    public function notifications(): NotificationRegistry
    {
        return $this->notifications;
    }

    public function payments(): PaymentRegistry
    {
        return $this->payments;
    }

    public function permissions(): PermissionRegistry
    {
        return $this->permissions;
    }
}
