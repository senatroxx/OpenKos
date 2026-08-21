<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\MoneyConverter;

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
                'reminder_message_templates' => reminderTemplates(),
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
                ->has('settings.reminder_message_templates.upcoming')
                ->has('settings.reminder_message_templates.due_today')
                ->has('settings.reminder_message_templates.overdue')
                ->has('defaultTemplates.upcoming')
                ->has('defaultTemplates.due_today')
                ->has('defaultTemplates.overdue')
                ->has('previewInvoiceContext')
                ->has('previewInvoiceLink')
            );
    });

    it('uses the configured currency for the preview amount', function () {
        $owner = User::factory()->owner()->create();
        Setting::set('currency', 'USD');
        Setting::set('locale', 'en');
        $expectedAmount = app(MoneyConverter::class)->format('1500000', 'USD', 'en');

        $this->actingAs($owner)
            ->get(route('settings.reminders.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('previewAmount', $expectedAmount)
                ->where('previewInvoiceContext', fn (string $context): bool => str_contains($context, $expectedAmount))
            );
    });

    it('updates reminder settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.reminders.edit'))->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_enabled' => true,
                'reminder_days_before' => 5,
                'reminder_overdue_intervals' => '2, 5, 10',
                'reminder_message_templates' => reminderTemplates(),
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
                'reminder_message_templates' => reminderTemplates(),
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
                'reminder_message_templates' => reminderTemplates(),
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
                'reminder_message_templates' => reminderTemplates(),
                'reminder_channels' => ['log'],
            ])
            ->assertSessionHasErrors(['reminder_days_before', 'reminder_overdue_intervals']);
    });

    it('updates reminder templates by reminder type', function () {
        $owner = User::factory()->owner()->create();
        $templates = [
            'upcoming' => 'Upcoming :name :invoice_context :invoice_link',
            'due_today' => 'Due today :name',
            'overdue' => 'Overdue :name :days',
        ];

        $this->actingAs($owner)
            ->patch(route('settings.reminders.update'), [
                'reminder_days_before' => 3,
                'reminder_overdue_intervals' => '1, 3, 7',
                'reminder_message_templates' => $templates,
                'reminder_channels' => ['log'],
            ])
            ->assertRedirect();

        expect(Setting::get('reminder_message_templates'))->toBe($templates);
    });
});

function reminderTemplates(): array
{
    return [
        'upcoming' => '',
        'due_today' => '',
        'overdue' => '',
    ];
}
