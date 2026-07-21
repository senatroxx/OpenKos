<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('tenant sees their portal dashboard', function () {
    $user = User::factory()->create(['email' => 'tenant@example.com']);
    $tenant = Tenant::factory()->withUser($user)->create(['name' => 'Budi']);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'rent_amount' => 1_500_000,
    ]);
    Invoice::factory()->create([
        'lease_id' => $lease->id,
        'due_date' => now()->addDays(3),
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    ReminderLog::factory()->create(['lease_id' => $lease->id]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/dashboard')
            ->where('tenant.name', 'Budi')
            ->where('lease.id', $lease->id)
            ->has('rent.upcoming_invoices', 1)
            ->has('notifications', 1));
});

test('portal displays overdue for past payable invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    Invoice::factory()->create([
        'lease_id' => $lease->id,
        'due_date' => now()->subDay(),
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rent.status', 'overdue')
            ->where('rent.upcoming_invoices.0.status', 'pending')
            ->where('rent.upcoming_invoices.0.display_status', 'overdue'));
});

test('portal displays overdue for past partial invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    Invoice::factory()->create([
        'lease_id' => $lease->id,
        'due_date' => now()->subDay(),
        'status' => InvoiceStatus::Partial,
    ]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rent.status', 'overdue')
            ->where('rent.upcoming_invoices.0.status', 'partial')
            ->where('rent.upcoming_invoices.0.display_status', 'overdue'));
});

test('portal only exposes the authenticated tenants lease data', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    Invoice::factory()->create(['lease_id' => $lease->id]);

    $otherTenant = Tenant::factory()->withUser()->create();
    $otherLease = Lease::factory()->create(['primary_tenant_id' => $otherTenant->id]);
    Invoice::factory()->create(['lease_id' => $otherLease->id]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lease.id', $lease->id)
            ->has('rent.upcoming_invoices', 1)
            ->where('rent.upcoming_invoices.0.lease_id', $lease->id));
});

test('tenant sees their active and historical leases', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $activeLease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now(),
        'rent_amount' => 1_500_000,
        'rent_due_day' => 5,
    ]);
    $historicalLease = Lease::factory()->terminated()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subYear(),
    ]);

    $this->actingAs($user)
        ->get(route('portal.lease.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/index')
            ->has('leases.data', 2)
            ->where('leases.data.0.id', $activeLease->id)
            ->where('leases.data.0.rent_due_day', 5)
            ->where('leases.data.1.id', $historicalLease->id));
});

test('tenant sees only their lease invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
        'amount_paid' => 500_000,
        'status' => InvoiceStatus::Partial,
    ]);
    $payment = Payment::factory()->pending()->create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000,
        'payment_date' => now(),
    ]);

    $otherTenant = Tenant::factory()->withUser()->create();
    $otherLease = Lease::factory()->create(['primary_tenant_id' => $otherTenant->id]);
    $otherInvoice = Invoice::factory()->create(['lease_id' => $otherLease->id]);
    Payment::factory()->create(['invoice_id' => $otherInvoice->id]);

    $this->actingAs($user)
        ->get(route('portal.lease.invoices', $lease))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/invoices')
            ->where('lease.id', $lease->id)
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $invoice->id)
            ->where('invoices.data.0.outstanding', '1000000.00'));

    $this->get(route('portal.lease.invoices.show', [$lease, $invoice]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/invoice')
            ->where('invoice.id', $invoice->id));
});

test('tenant can open only their own lease workspace', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $otherLease = Lease::factory()->create();

    $this->actingAs($user)
        ->get(route('portal.lease.show', $lease))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/show')
            ->where('lease.id', $lease->id));

    $this->get(route('portal.lease.show', $otherLease))
        ->assertNotFound();
});

test('tenant sees their lease unit transfer history', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $targetUnit = Unit::factory()->create(['property_id' => $lease->unit->property_id]);
    $history = LeaseUnitHistory::create([
        'lease_id' => $lease->id,
        'from_unit_id' => $lease->unit_id,
        'to_unit_id' => $targetUnit->id,
        'reason' => 'maintenance',
        'effective_date' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('portal.lease.history', $lease))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/history')
            ->has('history.data', 1)
            ->where('history.data.0.id', $history->id)
            ->where('history.data.0.reason', 'maintenance'));
});

test('user without tenant profile cannot access portal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

test('user without tenant profile cannot access portal invoices', function () {
    $lease = Lease::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('portal.lease.invoices', $lease))
        ->assertForbidden();
});

test('tenant without active lease does not show paid rent status', function () {
    $user = User::factory()->create();
    Tenant::factory()->withUser($user)->create();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lease', null)
            ->where('rent.status', 'none')
            ->where('rent.upcoming_invoices', []));
});

test('tenant without dashboard permission cannot access owner dashboard', function () {
    $user = User::factory()->create();
    Tenant::factory()->withUser($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('tenant login redirects to portal dashboard', function () {
    $user = User::factory()->create(['email' => 'tenant@example.com']);
    Tenant::factory()->withUser($user)->create();

    $this->post(route('login.store'), [
        'email' => 'tenant@example.com',
        'password' => 'password',
    ])->assertRedirect(route('portal.dashboard'));
});
