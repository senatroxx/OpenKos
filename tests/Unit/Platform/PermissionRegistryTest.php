<?php

use OpenKOS\Platform\Permission\PermissionRegistry;

it('registers permissions with an optional label', function () {
    $registry = new PermissionRegistry;

    $registry->register('example.view', 'View example')
        ->register('example.manage');

    expect($registry->all())->toBe([
        'example.view' => 'View example',
        'example.manage' => 'example.manage',
    ])
        ->and($registry->has('example.view'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse();
});

it('is idempotent — re-registering updates the label, no duplicates', function () {
    $registry = new PermissionRegistry;

    $registry->register('example.view')->register('example.view', 'View example');

    expect($registry->all())->toBe(['example.view' => 'View example']);
});

it('serializes to name/label pairs', function () {
    $registry = new PermissionRegistry;
    $registry->register('example.view', 'View example');

    expect($registry->toArray())->toBe([
        ['name' => 'example.view', 'label' => 'View example'],
    ]);
});
