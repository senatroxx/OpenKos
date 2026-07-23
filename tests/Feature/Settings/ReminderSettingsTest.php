<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('Reminder settings page', function () {
    it('forbids tenant-linked users from viewing the page', function () {
        $tenantUser = User::factory()->create();
        Tenant::factory()->withUser($tenantUser)->create();

        $this->actingAs($tenantUser)
            ->get(route('settings.reminders.edit'))
            ->assertForbidden();
    });

    it('forbids tenant-linked users from updating settings', function () {
        $tenantUser = User::factory()->create();
        Tenant::factory()->withUser($tenantUser)->create();

        $this->actingAs($tenantUser)
            ->patch(route('settings.reminders.update'), [
                'reminder_enabled' => true,
                'reminder_days_before' => 3,
                'reminder_overdue_intervals' => '1, 3, 7',
                'reminder_channels' => ['log'],
            ])
            ->assertForbidden();
    });

    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->get(route('settings.reminders.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/reminders')
                ->has('settings.reminder_enabled')
                ->has('settings.reminder_days_before')
                ->has('settings.reminder_overdue_intervals')
            );
    });

    it('updates reminder settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_enabled' => true,
                'reminder_days_before' => 5,
                'reminder_overdue_intervals' => '2, 5, 10',
                'reminder_channels' => ['log'],
            ])
            ->assertRedirect(route('settings.reminders.edit'));

        expect(Setting::get('reminder_enabled'))->toBeTrue();
        expect(Setting::get('reminder_days_before'))->toBe(5);
        expect(Setting::get('reminder_overdue_intervals'))->toBe([2, 5, 10]);
    });

    it('initial default is log channel', function () {
        expect(Setting::get('reminder_channels'))->toBe(['log']);
    });

    it('updates reminder channels', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_days_before' => 3,
                'reminder_overdue_intervals' => '1, 3, 7',
                'reminder_channels' => ['log', 'whatsapp', 'mail'],
            ])
            ->assertRedirect();

        expect(Setting::get('reminder_channels'))->toBe(['log', 'whatsapp', 'mail']);
    });

    it('requires at least one reminder channel', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_days_before' => 3,
                'reminder_overdue_intervals' => '1, 3, 7',
                'reminder_channels' => [],
            ])
            ->assertSessionHasErrors(['reminder_channels']);
    });

    it('validates reminder settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_days_before' => 100,
                'reminder_overdue_intervals' => 'invalid',
                'reminder_channels' => ['log'],
            ])
            ->assertSessionHasErrors(['reminder_days_before', 'reminder_overdue_intervals']);
    });
});
