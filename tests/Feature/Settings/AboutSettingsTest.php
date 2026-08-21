<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('guests cannot view the about page', function () {
    $this->get(route('settings.about.edit'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the about page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.about.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/about')
            ->where('product.name', 'OpenKOS')
            ->where('product.licenseName', 'Apache License 2.0')
            ->where('product.logoUrl', '/assets/brand/openkos-logo.svg')
            ->has('build.version')
            ->has('build.channel')
            ->where('platform.settings.0.key', 'about'));
});

test('the about page keeps OpenKOS branding separate from the site name', function () {
    $user = User::factory()->create();
    Setting::set('site_name', 'Casa Budi');

    $this->actingAs($user)
        ->get(route('settings.about.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Casa Budi')
            ->where('setting.site_name', 'Casa Budi')
            ->where('product.name', 'OpenKOS'));
});

test('the bundled license is available without an external request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.about.license'))
        ->assertSuccessful()
        ->assertSee('Apache License');
});
