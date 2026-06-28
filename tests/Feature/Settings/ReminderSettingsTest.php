<?php

use App\Models\Setting;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('Reminder settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
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

        $this->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_enabled' => true,
                'reminder_days_before' => 5,
                'reminder_overdue_intervals' => [2, 5, 10],
            ])
            ->assertRedirect(route('settings.reminders.edit'));

        $settings = Setting::get();

        expect($settings->reminder_enabled)->toBeTrue();
        expect($settings->reminder_days_before)->toBe(5);
        expect($settings->reminder_overdue_intervals)->toBe([2, 5, 10]);
    });

    it('validates reminder settings', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_days_before' => 100,
                'reminder_overdue_intervals' => ['invalid'],
            ])
            ->assertSessionHasErrors(['reminder_days_before', 'reminder_overdue_intervals.0']);
    });
});
