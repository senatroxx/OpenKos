<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia;

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
            ->has('platform.dashboard')
            ->where('platform.settings.0.key', 'general')
            ->where('platform.settings.0.group', null)
            ->where('platform.settings.1.key', 'about')
            ->where('platform.settings.1.group', null)
            ->where('platform.settings.2.key', 'profile')
            ->where('platform.settings.2.group', 'Account')
            ->where('platform.settings.3.key', 'reminders')
            ->where('platform.settings.3.group', 'Notifications')
            ->where('platform.settings.4.key', 'mail')
            ->where('platform.settings.4.group', 'Integrations')
            ->where('platform.settings.5.key', 'payment-gateway')
            ->where('platform.settings.5.group', 'Integrations'));
});
