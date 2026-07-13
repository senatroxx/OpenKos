<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    if (! file_exists(storage_path('installed'))) {
        file_put_contents(storage_path('installed'), now()->toDateTimeString());
    }
});

afterEach(function () {
    $file = storage_path('installed');
    if (file_exists($file)) {
        unlink($file);
    }
});

it('shares platform registry data with every Inertia page', function () {
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
