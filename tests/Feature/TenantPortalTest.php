<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\ReminderLog;
use App\Models\Tenant;
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

test('user without tenant profile cannot access portal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.dashboard'))
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
