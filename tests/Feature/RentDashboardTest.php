<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('collection queue loads with data for owner', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    Lease::factory()
        ->has(Invoice::factory()->state([
            'due_date' => '2026-07-05',
            'total' => 100000,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Pending,
        ]))
        ->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data')
            ->has('outstanding')
            ->has('tab_counts')
            ->has('recent_payments')
            ->has('recent_reminders')
        );

    Carbon::setTestNow();
});

test('collection queue includes invoice detail payload', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $tenant = Tenant::factory()->create(['name' => 'Rina Putri']);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'reference' => 'INV-QUEUE-001',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'due_date' => '2026-07-05',
        'total' => 500000,
        'amount_paid' => 250000,
        'status' => InvoiceStatus::Partial,
    ]);

    InvoiceLineItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Monthly Rent',
        'amount' => 500000,
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 250000,
        'payment_date' => '2026-07-08',
        'payment_method' => 'transfer',
        'status' => PaymentStatus::Pending,
    ]);

    PaymentProof::factory()->create([
        'payment_id' => $payment->id,
        'original_name' => 'receipt.png',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entries.data.0.reference', 'INV-QUEUE-001')
            ->where('entries.data.0.line_items.0.description', 'Monthly Rent')
            ->where('entries.data.0.payments.0.amount', '250000.00')
            ->where('entries.data.0.payments.0.proofs.0.original_name', 'receipt.png')
        );

    Carbon::setTestNow();
});

test('collection queue shows correct outstanding count', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease1 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease1->id,
        'due_date' => '2026-07-03',
        'total' => 100000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease2 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease2->id,
        'due_date' => '2026-07-05',
        'total' => 200000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Partial,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->where('outstanding.count', 2)
            ->where('outstanding.amount', 300000)
        );

    Carbon::setTestNow();
});

test('collection queue tab counts are correct', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease1 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease1->id,
        'due_date' => '2026-07-05',
        'total' => 100000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease2 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease2->id,
        'due_date' => '2026-07-10',
        'total' => 200000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease3 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease3->id,
        'due_date' => '2026-07-15',
        'total' => 150000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease4 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease4->id,
        'due_date' => '2026-07-01',
        'total' => 500000,
        'amount_paid' => 500000,
        'status' => InvoiceStatus::Paid,
    ]);

    Payment::factory()->create([
        'invoice_id' => $lease1->invoices()->first()->id,
        'amount' => 100000,
        'payment_date' => '2026-07-09',
        'payment_method' => 'transfer',
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->where('tab_counts.all', 4)
            ->where('tab_counts.overdue', 1)
            ->where('tab_counts.due_today', 1)
            ->where('tab_counts.pending_review', 1)
            ->where('tab_counts.paid', 1)
        );

    Carbon::setTestNow();
});

test('collection queue overdue tab is selected', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease1 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease1->id,
        'due_date' => '2026-07-05',
        'total' => 100000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease2 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease2->id,
        'due_date' => '2026-07-10',
        'total' => 200000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['urgency' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.total', 1)
            ->where('tab_counts.all', 3)
        );

    Carbon::setTestNow();
});

test('collection queue pending review tab lists invoices with pending payments', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $reviewLease = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    $reviewInvoice = Invoice::factory()->create([
        'lease_id' => $reviewLease->id,
        'due_date' => '2026-07-12',
        'total' => 200000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    Payment::factory()->create([
        'invoice_id' => $reviewInvoice->id,
        'amount' => 200000,
        'payment_date' => '2026-07-09',
        'payment_method' => 'transfer',
        'status' => PaymentStatus::Pending,
    ]);

    $otherLease = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $otherLease->id,
        'due_date' => '2026-07-05',
        'total' => 100000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['urgency' => 'pending_review']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.id', $reviewInvoice->id)
            ->where('entries.data.0.pending_payment_review_count', 1)
            ->where('tab_counts.pending_review', 1)
            ->where('tab_counts.all', 2)
        );

    Carbon::setTestNow();
});

test('collection queue paid tab works', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease1 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease1->id,
        'due_date' => '2026-07-05',
        'total' => 100000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $lease2 = Lease::factory()->create(['status' => 'active', 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease2->id,
        'due_date' => '2026-07-10',
        'total' => 200000,
        'amount_paid' => 200000,
        'status' => InvoiceStatus::Paid,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['urgency' => 'paid']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
        );

    Carbon::setTestNow();
});

test('collection queue search filters by tenant name', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $tenantA = Tenant::factory()->create(['name' => 'Budi Santoso']);
    $leaseA = Lease::factory()->create([
        'primary_tenant_id' => $tenantA->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $leaseA->tenants()->sync([$tenantA->id => ['is_primary' => true]]);
    Invoice::factory()->create([
        'lease_id' => $leaseA->id,
        'due_date' => '2026-07-05',
        'status' => InvoiceStatus::Pending,
    ]);

    $tenantB = Tenant::factory()->create(['name' => 'Siti Rahma']);
    $leaseB = Lease::factory()->create([
        'primary_tenant_id' => $tenantB->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $leaseB->tenants()->sync([$tenantB->id => ['is_primary' => true]]);
    Invoice::factory()->create([
        'lease_id' => $leaseB->id,
        'due_date' => '2026-07-05',
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['search' => 'budi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.tenant_name', 'Budi Santoso')
        );

    Carbon::setTestNow();
});

test('collection queue requires authentication', function () {
    $this->get(route('dashboard.rent'))->assertRedirect('login');
});

test('collection queue requires dashboard.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertForbidden();
});

test('collection queue shows recent payments', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $tenant = Tenant::factory()->create(['name' => 'Alex Wijaya']);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'total' => 500000,
        'amount_paid' => 500000,
        'status' => InvoiceStatus::Paid,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 500000,
        'payment_date' => '2026-07-08',
        'payment_method' => 'transfer',
        'status' => PaymentStatus::Confirmed,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->has('recent_payments', 1)
            ->where('recent_payments.0.tenant_name', 'Alex Wijaya')
            ->where('recent_payments.0.payment_method', 'transfer')
        );

    Carbon::setTestNow();
});

test('collection queue shows recent reminders', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $tenant = Tenant::factory()->create(['name' => 'Dewi Lestari']);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);

    ReminderLog::factory()->create([
        'lease_id' => $lease->id,
        'reminder_type' => 'overdue',
        'channel' => 'whatsapp',
        'sent_at' => '2026-07-09 10:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->has('recent_reminders', 1)
            ->where('recent_reminders.0.tenant_name', 'Dewi Lestari')
            ->where('recent_reminders.0.reminder_type', 'overdue')
        );

    Carbon::setTestNow();
});

test('collection queue scopes to user properties for non-owner', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->admin()->create();

    $propertyA = Property::factory()->create();
    $unitA = Unit::factory()->create(['property_id' => $propertyA->id]);
    $tenantA = Tenant::factory()->create(['name' => 'Budi Property A']);
    $leaseA = Lease::factory()->create([
        'unit_id' => $unitA->id,
        'primary_tenant_id' => $tenantA->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $leaseA->tenants()->sync([$tenantA->id => ['is_primary' => true]]);
    Invoice::factory()->create([
        'lease_id' => $leaseA->id,
        'due_date' => '2026-07-05',
        'status' => InvoiceStatus::Pending,
    ]);

    $propertyB = Property::factory()->create();
    $unitB = Unit::factory()->create(['property_id' => $propertyB->id]);
    $tenantB = Tenant::factory()->create(['name' => 'Siti Property B']);
    $leaseB = Lease::factory()->create([
        'unit_id' => $unitB->id,
        'primary_tenant_id' => $tenantB->id,
        'start_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $leaseB->tenants()->sync([$tenantB->id => ['is_primary' => true]]);
    Invoice::factory()->create([
        'lease_id' => $leaseB->id,
        'due_date' => '2026-07-05',
        'status' => InvoiceStatus::Pending,
    ]);

    $propertyA->users()->attach($user->id);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.tenant_name', 'Budi Property A')
        );

    Carbon::setTestNow();
});

test('collection queue shows empty state when no data', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 0)
            ->has('recent_payments', 0)
            ->has('recent_reminders', 0)
        );

    Carbon::setTestNow();
});
