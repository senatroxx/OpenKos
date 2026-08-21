<?php

use App\Data\Reminder\ReminderEvent;
use App\Enums\PaymentStatus;
use App\Enums\ReminderType;
use App\Events\Maintenance\MaintenanceTicketUpdated;
use App\Events\Payment\PaymentStatusChanged;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\LogChannel;
use App\Notifications\RentReminder;
use App\Notifications\TenantPortalNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('tenant can view and mark notifications as read', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $tenant->notify(new TenantPortalNotification([
        'type' => 'maintenance_created',
        'title' => 'Maintenance update',
        'message' => 'Leaking faucet',
        'url' => null,
    ]));

    $notification = $tenant->notifications()->first();

    $this->actingAs($user)
        ->get(route('portal.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/notifications/index')
            ->where('unreadCount', 1)
            ->where('notifications.data.0.title', 'Maintenance update'));

    $this->post(route('portal.notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('tenant cannot mark another tenants notification as read', function () {
    $user = User::factory()->create();
    Tenant::factory()->withUser($user)->create();
    $otherTenant = Tenant::factory()->withUser()->create();
    $otherTenant->notify(new TenantPortalNotification([
        'type' => 'maintenance_created',
        'title' => 'Private',
        'message' => 'Private',
    ]));
    $notification = $otherTenant->notifications()->first();

    $this->actingAs($user)
        ->post(route('portal.notifications.read', $notification->id))
        ->assertNotFound();
});

test('tenant can mark all notifications as read', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();

    foreach (['First', 'Second'] as $title) {
        $tenant->notify(new TenantPortalNotification([
            'type' => 'maintenance_created',
            'title' => $title,
            'message' => $title,
        ]));
    }

    $this->actingAs($user)
        ->post(route('portal.notifications.read-all'))
        ->assertRedirect();

    expect($tenant->unreadNotifications()->count())->toBe(0);
});

test('payment confirmation creates a tenant notification', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    $payment = $invoice->payments()->create([
        'amount' => 100000,
        'payment_date' => now(),
        'status' => PaymentStatus::Confirmed,
    ]);

    PaymentStatusChanged::dispatch($payment, PaymentStatus::Pending, PaymentStatus::Confirmed);

    expect($tenant->notifications()->where('type', 'payment_confirmed')->exists())->toBeTrue();
});

test('non-confirmed payment status changes do not notify tenants', function () {
    $tenant = Tenant::factory()->withUser()->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    $payment = $invoice->payments()->create([
        'amount' => 100000,
        'payment_date' => now(),
        'status' => PaymentStatus::Pending,
    ]);

    PaymentStatusChanged::dispatch($payment, PaymentStatus::Pending, PaymentStatus::Pending);

    expect($tenant->notifications()->where('type', 'payment_confirmed')->exists())->toBeFalse();
});

test('rent reminder channels contain database only once', function () {
    Setting::set('reminder_channels', ['database', 'log'], 'array');
    $lease = Lease::factory()->create();
    $reminder = new RentReminder(new ReminderEvent(
        lease: $lease,
        type: ReminderType::Upcoming,
        periodStart: today()->toDateString(),
        periodEnd: today()->addMonth()->toDateString(),
        dueDate: today()->addDays(3)->toDateString(),
        amount: 100000,
        currency: 'IDR',
    ));

    expect($reminder->via($lease->primaryTenant))->toHaveCount(2)
        ->and($reminder->via($lease->primaryTenant))->toEqual(['database', LogChannel::class]);
});

test('maintenance no-op updates do not notify tenants', function () {
    $ticket = MaintenanceTicket::factory()->create();
    $owner = User::factory()->owner()->create();
    $owner->properties()->attach($ticket->property_id);
    Event::fake([MaintenanceTicketUpdated::class]);

    $this->actingAs($owner)
        ->put(route('maintenance-tickets.update', $ticket), [
            'title' => $ticket->title,
        ])
        ->assertRedirect();

    Event::assertNotDispatched(MaintenanceTicketUpdated::class);
});

test('maintenance ticket creation creates a tenant notification', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Leaking faucet',
            'location_type' => 'unit',
        ])
        ->assertRedirect();

    expect($tenant->notifications()->where('type', 'maintenance_created')->exists())->toBeTrue();
});

test('maintenance ticket updates create a tenant notification', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $ticket = MaintenanceTicket::factory()->create([
        'created_by' => $user->id,
        'title' => 'Leaking faucet',
    ]);

    MaintenanceTicketUpdated::dispatch($ticket);

    expect($tenant->notifications()->where('type', 'maintenance_updated')->exists())->toBeTrue();
});

test('invoice reminder events notify tenants with a configured route', function () {
    Notification::fake();
    Setting::set('reminder_channels', ['log'], 'array');

    $lease = Lease::factory()->create();
    $tenant = $lease->primaryTenant;
    $event = new ReminderEvent(
        lease: $lease,
        type: ReminderType::Upcoming,
        periodStart: today()->toDateString(),
        periodEnd: today()->addMonth()->toDateString(),
        dueDate: today()->addDays(3)->toDateString(),
        amount: 100000,
        currency: 'IDR',
    );

    InvoiceReminderDispatched::dispatch($event);

    Notification::assertSentTo($tenant, RentReminder::class);
});

test('lease expiration command creates only one notification at thirty days', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'end_date' => today()->addDays(30),
    ]);

    $this->artisan('app:send-lease-expiration-notifications')->assertSuccessful();
    $this->artisan('app:send-lease-expiration-notifications')->assertSuccessful();

    expect($tenant->notifications()->where('type', 'lease_expiring')->count())->toBe(1);
});
