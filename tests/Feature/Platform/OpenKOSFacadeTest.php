<?php

use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;

it('proxies to the singleton manager', function () {
    expect(OpenKOS::getFacadeRoot())->toBe(app(OpenKOSManager::class));
});

it('makes facade registrations visible through the container', function () {
    OpenKOS::navigation()->registerItem(new NavigationItem(title: 'Via Facade'), 'facade-test');

    expect(app(OpenKOSManager::class)->navigation()->items('facade-test'))
        ->toHaveCount(1)
        ->and(OpenKOS::navigation()->items('facade-test')[0]->title)->toBe('Via Facade');
});
