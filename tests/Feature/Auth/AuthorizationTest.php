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

    expect($user->can('properties.manage'))->toBeTrue();
    expect($user->can('rooms.manage'))->toBeTrue();
    expect($user->can('tenants.manage'))->toBeTrue();
    expect($user->can('financials.view'))->toBeTrue();
    expect($user->can('reports.view'))->toBeTrue();
    expect($user->can('dashboard.view'))->toBeTrue();
});

test('admin cannot manage users, roles, or financials', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('users.manage'))->toBeFalse();
    expect($user->can('roles.manage'))->toBeFalse();
    expect($user->can('financials.manage'))->toBeFalse();
});

test('staff has limited permissions', function () {
    $user = User::factory()->staff()->create();

    expect($user->can('tenants.manage'))->toBeTrue();
    expect($user->can('dashboard.view'))->toBeTrue();
});

test('staff cannot access admin permissions', function () {
    $user = User::factory()->staff()->create();

    expect($user->can('properties.manage'))->toBeFalse();
    expect($user->can('rooms.manage'))->toBeFalse();
    expect($user->can('financials.view'))->toBeFalse();
    expect($user->can('reports.view'))->toBeFalse();
    expect($user->can('users.manage'))->toBeFalse();
    expect($user->can('roles.manage'))->toBeFalse();
    expect($user->can('financials.manage'))->toBeFalse();
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
