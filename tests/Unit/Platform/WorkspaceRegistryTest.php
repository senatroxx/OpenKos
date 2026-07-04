<?php

use OpenKOS\Platform\Workspace\WorkspaceRegistry;
use OpenKOS\Platform\Workspace\WorkspaceTab;

it('memoizes workspaces by name', function () {
    $registry = new WorkspaceRegistry;

    expect($registry->for('property'))->toBe($registry->for('property'))
        ->and($registry->for('property'))->not->toBe($registry->for('lease'));
});

it('keeps tabs scoped to their workspace', function () {
    $registry = new WorkspaceRegistry;
    $registry->for('property')->registerTab(new WorkspaceTab(key: 'inspections', label: 'Inspections'));

    expect($registry->for('property')->tabs())->toHaveKey('inspections')
        ->and($registry->for('lease')->tabs())->toBe([]);
});

it('throws on duplicate tab keys within a workspace', function () {
    $workspace = (new WorkspaceRegistry)->for('property');
    $workspace->registerTab(new WorkspaceTab(key: 'example', label: 'Example'));

    $workspace->registerTab(new WorkspaceTab(key: 'example', label: 'Again'));
})->throws(InvalidArgumentException::class, 'Tab [example] is already registered on the [property] workspace.');

it('serializes all workspaces and tab shapes', function () {
    $registry = new WorkspaceRegistry;
    $registry->for('lease')->registerTab(new WorkspaceTab(
        key: 'insurance',
        label: 'Insurance',
        permission: 'leases.view',
        meta: ['badge' => 'new'],
    ));

    expect($registry->toArray())->toBe([
        'lease' => [[
            'key' => 'insurance',
            'label' => 'Insurance',
            'permission' => 'leases.view',
            'meta' => ['badge' => 'new'],
        ]],
    ]);
});
