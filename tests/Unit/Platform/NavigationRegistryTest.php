<?php

use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\Navigation\NavigationRegistry;

it('registers and lists items per group', function () {
    $registry = new NavigationRegistry;
    $main = new NavigationItem(title: 'Dashboard', href: '/dashboard');
    $footer = new NavigationItem(title: 'Docs', href: '/docs');

    $registry->registerItem($main)->registerItem($footer, 'footer');

    expect($registry->items('main'))->toBe([$main])
        ->and($registry->items('footer'))->toBe([$footer])
        ->and($registry->items())->toBe([$main, $footer])
        ->and($registry->items('missing'))->toBe([]);
});

it('serializes to the frontend NavItem shape with nested children', function () {
    $registry = new NavigationRegistry;
    $registry->registerItem(new NavigationItem(
        title: 'Dashboard',
        icon: 'layout-dashboard',
        permission: 'dashboard.view',
        children: [new NavigationItem(title: 'Overview', href: '/dashboard')],
    ));

    expect($registry->toArray())->toBe([
        'main' => [[
            'title' => 'Dashboard',
            'href' => null,
            'icon' => 'layout-dashboard',
            'permission' => 'dashboard.view',
            'children' => [[
                'title' => 'Overview',
                'href' => '/dashboard',
                'icon' => null,
                'permission' => null,
                'children' => [],
            ]],
        ]],
    ]);
});

it('returns no items when empty', function () {
    expect((new NavigationRegistry)->items())->toBe([]);
});
