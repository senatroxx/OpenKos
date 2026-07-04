<?php

use OpenKOS\Platform\Dashboard\DashboardPage;
use OpenKOS\Platform\Dashboard\DashboardRegistry;

it('registers pages keyed by page key', function () {
    $registry = new DashboardRegistry;
    $page = new DashboardPage(key: 'rent', title: 'Rent', href: '/dashboard/rent', permission: 'dashboard.view');

    $registry->registerPage($page);

    expect($registry->pages())->toBe(['rent' => $page])
        ->and($registry->toArray())->toBe([[
            'key' => 'rent',
            'title' => 'Rent',
            'href' => '/dashboard/rent',
            'permission' => 'dashboard.view',
        ]]);
});

it('throws on duplicate page keys', function () {
    $registry = new DashboardRegistry;
    $registry->registerPage(new DashboardPage(key: 'rent', title: 'Rent', href: '/rent'));

    $registry->registerPage(new DashboardPage(key: 'rent', title: 'Other', href: '/other'));
})->throws(InvalidArgumentException::class, 'Dashboard page [rent] is already registered.');
