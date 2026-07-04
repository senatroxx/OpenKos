<?php

use OpenKOS\Platform\Settings\SettingsPage;
use OpenKOS\Platform\Settings\SettingsRegistry;

it('registers pages keyed by page key', function () {
    $registry = new SettingsRegistry;
    $page = new SettingsPage(key: 'billing', title: 'Billing', href: '/settings/billing');

    $registry->registerPage($page);

    expect($registry->pages())->toBe(['billing' => $page])
        ->and($registry->toArray())->toBe([[
            'key' => 'billing',
            'title' => 'Billing',
            'href' => '/settings/billing',
            'permission' => null,
        ]]);
});

it('throws on duplicate page keys', function () {
    $registry = new SettingsRegistry;
    $registry->registerPage(new SettingsPage(key: 'billing', title: 'Billing', href: '/a'));

    $registry->registerPage(new SettingsPage(key: 'billing', title: 'Other', href: '/b'));
})->throws(InvalidArgumentException::class, 'Settings page [billing] is already registered.');
