<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('owner has all permissions', function () {
    $user = User::factory()->owner()->create();

    expect($user->can('non-existent-permission'))->toBeTrue();
});

test('admin has operational permissions', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('properties.view'))->toBeTrue();
    expect($user->can('units.view'))->toBeTrue();
    expect($user->can('tenants.view'))->toBeTrue();
    expect($user->can('leases.view'))->toBeTrue();
    expect($user->can('financials.view'))->toBeTrue();
    expect($user->can('reports.view'))->toBeTrue();
    expect($user->can('dashboard.view'))->toBeTrue();
    expect($user->can('users.view'))->toBeTrue();
    expect($user->can('users.update'))->toBeTrue();
});

test('admin cannot manage users, roles, or financials beyond view', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('users.create'))->toBeFalse();
    expect($user->can('users.delete'))->toBeFalse();
    expect($user->can('roles.view'))->toBeFalse();
    expect($user->can('roles.create'))->toBeFalse();
    expect($user->can('financials.view'))->toBeTrue();
});

test('staff has limited permissions', function () {
    $user = User::factory()->staff()->create();

    expect($user->can('tenants.view'))->toBeTrue();
    expect($user->can('tenants.update'))->toBeTrue();
    expect($user->can('leases.view'))->toBeTrue();
    expect($user->can('dashboard.view'))->toBeTrue();
});

test('staff cannot access admin permissions', function () {
    $user = User::factory()->staff()->create();

    expect($user->can('properties.view'))->toBeFalse();
    expect($user->can('units.view'))->toBeFalse();
    expect($user->can('financials.view'))->toBeFalse();
    expect($user->can('reports.view'))->toBeFalse();
    expect($user->can('users.view'))->toBeFalse();
    expect($user->can('roles.view'))->toBeFalse();
});

test('middleware blocks users without dashboard.view permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertForbidden();
});

test('middleware allows users with dashboard.view permission', function () {
    $user = User::factory()->staff()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});
