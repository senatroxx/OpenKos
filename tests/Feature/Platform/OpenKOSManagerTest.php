<?php

use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\Workspace;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;

it('resolves as a container singleton', function () {
    expect(app(OpenKOSManager::class))->toBe(app(OpenKOSManager::class));
});

it('exposes the container-bound registry singletons', function () {
    $manager = app(OpenKOSManager::class);

    expect($manager->dashboard())->toBe(app(DashboardRegistry::class))
        ->and($manager->navigation())->toBe(app(NavigationRegistry::class))
        ->and($manager->workspaces())->toBe(app(WorkspaceRegistry::class))
        ->and($manager->settings())->toBe(app(SettingsRegistry::class))
        ->and($manager->notifications())->toBe(app(NotificationRegistry::class))
        ->and($manager->payments())->toBe(app(PaymentRegistry::class));
});

it('returns correctly scoped workspaces', function () {
    $manager = app(OpenKOSManager::class);

    expect($manager->property())->toBeInstanceOf(Workspace::class)
        ->and($manager->property()->name)->toBe('property')
        ->and($manager->lease()->name)->toBe('lease')
        ->and($manager->tenant()->name)->toBe('tenant')
        ->and($manager->workspace('unit')->name)->toBe('unit')
        ->and($manager->property())->toBe($manager->workspace('property'));
});
