<?php

use App\Actions\Reminders\SendRentReminders;
use App\Business\Reminders\PaymentReminderScheduler;
use App\Data\Reminder\ReminderSettings;
use App\Models\Lease;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RentReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function createLeaseWithTenant(array $overrides = []): Lease
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create(['phone' => '628123456789']);

    return Lease::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(3),
        'rent_amount' => 1500000.00,
        'rent_due_day' => 1,
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'status' => 'active',
    ], $overrides));
}

describe('PaymentReminderScheduler', function () {
    it('returns upcoming event when days match', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-28'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $lease->load('payments');
        $settings = new ReminderSettings(true, 3, []);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('upcoming');
        expect($events[0]->dueDate)->toBe('2026-07-01');

        Carbon::setTestNow();
    });

    it('returns due today event', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $lease->load('payments');
        $settings = new ReminderSettings(true, 3, []);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('due_today');

        Carbon::setTestNow();
    });

    it('returns overdue event at configured interval', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-02'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load('payments');
        $settings = new ReminderSettings(true, 3, [1, 3, 7]);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toHaveCount(1);
        expect($events[0]->type->value)->toBe('overdue');
        expect($events[0]->overdueDays)->toBe(1);

        Carbon::setTestNow();
    });

    it('returns overdue for intervals well past the max', function () {
        Carbon::setTestNow(Carbon::parse('2026-08-10'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2024-01-01']);
        $lease->load('payments');
        $settings = new ReminderSettings(true, 3, [7]);

        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        $overdueEvents = array_filter($events, fn ($e) => $e->type->value === 'overdue');
        expect($overdueEvents)->not->toBeEmpty();
        foreach ($overdueEvents as $event) {
            expect($event->overdueDays)->toBe(7);
        }

        Carbon::setTestNow();
    });

    it('returns no events when lease is paid', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-06-01']);
        $lease->load('payments');

        $lease->payments()->create([
            'amount' => 1500000.00,
            'payment_date' => '2026-07-01',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'payment_method' => 'transfer',
            'status' => 'confirmed',
        ]);

        $lease->unsetRelation('payments');
        $lease->load('payments');

        $settings = new ReminderSettings(true, 3, []);
        $events = (new PaymentReminderScheduler)->pendingFor($lease, $settings);

        expect($events)->toBeEmpty();

        Carbon::setTestNow();
    });
});

describe('SendRentRemindersAction', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('sends reminder and creates log', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant', 'payments']);

        $action = app(SendRentReminders::class);
        $action->execute($lease);

        expect(ReminderLog::count())->toBe(1);
        Notification::assertSentTo($lease->primaryTenant, RentReminder::class);

        Carbon::setTestNow();
    });

    it('does not send duplicate reminders', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant', 'payments']);

        $action = app(SendRentReminders::class);
        $first = $action->execute($lease);
        $second = $action->execute($lease);

        expect($first)->toHaveCount(1);
        expect($second)->toBeEmpty();
        expect(ReminderLog::count())->toBe(1);
        Notification::assertSentToTimes($lease->primaryTenant, RentReminder::class, 1);

        Carbon::setTestNow();
    });

    it('does nothing when reminders disabled', function () {
        Setting::set('reminder_enabled', false, 'boolean');

        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1]);
        $lease->load(['primaryTenant', 'payments']);

        $action = app(SendRentReminders::class);
        $sent = $action->execute($lease);

        expect($sent)->toBeEmpty();
        Notification::assertNothingSent();

        Carbon::setTestNow();
    });

    it('skips tenants without phone', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['phone' => null]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
            'rent_amount' => 1500000.00,
            'rent_due_day' => 1,
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'status' => 'active',
        ]);
        $lease->load(['primaryTenant', 'payments']);

        $action = app(SendRentReminders::class);
        $sent = $action->execute($lease);

        expect($sent)->toBeEmpty();
        Notification::assertNothingSent();

        Carbon::setTestNow();
    });
});

describe('Command', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('sends reminders via artisan command', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant', 'payments']);

        $this->artisan('rent:send-reminders')
            ->expectsOutputToContain('Sent')
            ->assertSuccessful();

        Carbon::setTestNow();
    });

    it('handles manual send for single lease', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->load(['primaryTenant', 'payments']);

        $this->artisan('rent:send-reminders', ['lease' => $lease->id])
            ->expectsOutputToContain('Sent')
            ->assertSuccessful();

        Carbon::setTestNow();
    });
});

describe('Manual Send via Controller', function () {
    beforeEach(function () {
        Setting::set('reminder_channels', ['whatsapp'], 'array');
    });

    it('allows user with reminders.send permission to send reminder', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));
        Notification::fake();

        $owner = User::factory()->owner()->create();
        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->unit->property->users()->attach($owner);

        $this->actingAs($owner)
            ->post(route('leases.send-reminder', $lease))
            ->assertRedirect();

        expect(ReminderLog::count())->toBe(1);

        Carbon::setTestNow();
    });

    it('denies user without reminders.send permission', function () {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $admin = User::factory()->admin()->create();
        $admin->revokePermissionTo('reminders.send');

        $lease = createLeaseWithTenant(['rent_due_day' => 1, 'start_date' => '2026-07-01']);
        $lease->unit->property->users()->attach($admin);

        $this->actingAs($admin)
            ->post(route('leases.send-reminder', $lease))
            ->assertForbidden();

        Carbon::setTestNow();
    });
});
