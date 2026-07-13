<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia;

it('shares platform registry data with every Inertia page', function () {
    Setting::set('installed', true);
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('platform.navigation')
            ->has('platform.workspaces')
            ->has('platform.settings')
            ->has('platform.dashboard'));
});
