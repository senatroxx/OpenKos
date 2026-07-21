<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
    $this->owner = User::factory()->owner()->create();
});

describe('invoice workspace index', function () {
    it('lists invoices for a lease', function () {
        $lease = Lease::factory()->create();
        $invoices = Invoice::factory()
            ->count(3)
            ->sequence(
                ['period_start' => '2026-05-01'],
                ['period_start' => '2026-06-01'],
                ['period_start' => '2026-07-01'],
            )
            ->create(['lease_id' => $lease->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices', $lease))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('leases/invoices')
                ->where('lease.id', $lease->id)
                ->has('invoices.data', 3));
    });

    it('derives overdue display status without changing stored status', function () {
        $lease = Lease::factory()->create();
        Invoice::factory()->create([
            'lease_id' => $lease->id,
            'due_date' => now()->subDay(),
            'status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices', $lease))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoices.data.0.status', 'pending')
                ->where('invoices.data.0.display_status', 'overdue'));
    });

    it('returns empty state when lease has no invoices', function () {
        $lease = Lease::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices', $lease))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('invoices.data', 0));
    });

    it('denies users without leases.view permission', function () {
        $user = User::factory()->create();
        $lease = Lease::factory()->create();

        $this->actingAs($user)
            ->get(route('leases.workspace.invoices', $lease))
            ->assertForbidden();
    });
});

describe('invoice workspace show', function () {
    it('renders invoice detail with line items and payments', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
        InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Monthly rent',
            'amount' => $invoice->total,
        ]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('leases/invoice-detail')
                ->where('lease.id', $lease->id)
                ->where('invoice.id', $invoice->id)
                ->has('invoice.line_items', 1));
    });

    it('derives overdue display status on invoice detail', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create([
            'lease_id' => $lease->id,
            'due_date' => now()->subDay(),
            'status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoice.status', 'pending')
                ->where('invoice.display_status', 'overdue'));
    });

    it('denies access to invoice from another lease', function () {
        $lease = Lease::factory()->create();
        $otherLease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $otherLease->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertNotFound();
    });
});
