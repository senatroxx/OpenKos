<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            ->missing('leaseContext')
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

test('tenant sees their current and previous stays', function () {
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
            ->has('currentLeases', 1)
            ->where('currentLeases.0.id', $activeLease->id)
            ->where('currentLeases.0.rent_due_day', 5)
            ->has('previousLeases', 1)
            ->where('previousLeases.0.id', $historicalLease->id)
            ->missing('leaseContext'));
});

test('tenant sees only their invoices in billing', function () {
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
        ->get(route('portal.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/payments/index')
            ->has('actionableInvoices.data', 1)
            ->where('actionableInvoices.data.0.id', $invoice->id)
            ->where('actionableInvoices.data.0.outstanding', '1000000.00')
            ->where('actionableInvoices.data.0.payable_amount', '500000.00')
            ->where('outstandingSummary.amount', '500000.00')
            ->where('outstandingSummary.count', 1)
            ->where('outstandingSummary.next_due_date', $invoice->due_date->toDateString())
            ->where('outstandingSummary.pending_payment_count', 1)
            ->where('leaseContext.selected.unit_name', $lease->unit->name)
            ->where('leaseContext.selected.property_name', $lease->unit->property->name)
            ->has('historicalInvoices', 0));

    $this->get(route('portal.billing.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/payments/invoice')
            ->where('invoice.id', $invoice->id)
            ->missing('leaseContext'));

    $this->get(route('portal.billing.invoices.show', $otherInvoice))
        ->assertNotFound();
});

test('tenant sees only their billing data', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $actionRequiredInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $pendingInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => now()->addMonth()->startOfMonth(),
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);
    $recordInvoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => now()->addMonths(2)->startOfMonth(),
        'total' => 1_500_000,
        'amount_paid' => 1_500_000,
        'status' => InvoiceStatus::Paid,
    ]);

    $otherLease = Lease::factory()->create();
    Invoice::factory()->create(['lease_id' => $otherLease->id]);

    $pendingPayment = Payment::factory()->pending()->create([
        'invoice_id' => $pendingInvoice->id,
        'amount' => 1_500_000,
    ]);
    $historyPayment = Payment::factory()->create([
        'invoice_id' => $recordInvoice->id,
        'amount' => 1_500_000,
    ]);

    $this->actingAs($user)
        ->get(route('portal.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/payments/index')
            ->has('actionableInvoices.data', 1)
            ->where('actionableInvoices.data.0.id', $actionRequiredInvoice->id)
            ->has('historicalInvoices', 1)
            ->where('historicalInvoices.0.id', $recordInvoice->id)
            ->where('historicalInvoiceCount', 1)
            ->has('pendingPayments', 1)
            ->where('pendingPayments.0.id', $pendingPayment->id)
            ->has('finalizedPayments', 1)
            ->where('finalizedPayments.0.id', $historyPayment->id)
            ->where('finalizedPaymentCount', 1));

    $this->get(route('portal.billing.history.invoices'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/payments/invoice-history')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $recordInvoice->id));

    $this->get(route('portal.billing.history.payments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/payments/payment-history')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $historyPayment->id));

    $this->get('/portal/payments')->assertNotFound();
});

test('tenant can pay the remaining balance after a partial pending payment', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    Payment::factory()->pending()->create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000,
    ]);

    $this->actingAs($user)
        ->get(route('portal.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('actionableInvoices.data', 1)
            ->where('actionableInvoices.data.0.id', $invoice->id)
            ->where('actionableInvoices.data.0.payable_amount', '1000000.00')
            ->has('pendingPayments', 1));
});

test('tenant billing scopes data to the selected lease context', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $currentLease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $historicalLease = Lease::factory()->terminated()->create([
        'primary_tenant_id' => $tenant->id,
    ]);
    $historicalInvoice = Invoice::factory()->create([
        'lease_id' => $historicalLease->id,
        'status' => InvoiceStatus::Paid,
        'amount_paid' => 1_500_000,
    ]);
    Invoice::factory()->create(['lease_id' => $currentLease->id]);

    $otherTenant = Tenant::factory()->withUser()->create();
    $otherLease = Lease::factory()->create(['primary_tenant_id' => $otherTenant->id]);

    $this->actingAs($user)
        ->get(route('portal.billing.index', ['lease' => $historicalLease->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leaseContext.selected.id', $historicalLease->id)
            ->has('actionableInvoices.data', 0)
            ->has('historicalInvoices', 1)
            ->where('historicalInvoices.0.id', $historicalInvoice->id));

    $this->get(route('portal.billing.index', ['lease' => $otherLease->id]))
        ->assertNotFound();
});

test('tenant billing loads actionable invoices in pages', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    foreach (range(0, 5) as $month) {
        Invoice::factory()->create([
            'lease_id' => $lease->id,
            'period_start' => now()->subMonths($month)->startOfMonth(),
            'status' => InvoiceStatus::Pending,
        ]);
    }

    $this->actingAs($user)
        ->get(route('portal.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('actionableInvoices.data', 5)
            ->where('actionableInvoices.current_page', 1)
            ->where('actionableInvoices.last_page', 2)
            ->where('outstandingSummary.count', 6)
            ->has('pendingPayments', 0));

    $this->get(route('portal.billing.index', ['action_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('actionableInvoices.data', 1)
            ->where('actionableInvoices.current_page', 2));
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
            ->where('lease.id', $lease->id)
            ->missing('leaseContext'));

    $this->get(route('portal.lease.show', $otherLease))
        ->assertNotFound();
});

test('tenant submits a pending invoice payment for verification', function () {
    Storage::fake('local');

    $tenantUser = User::factory()->create();
    $tenant = Tenant::factory()->withUser($tenantUser)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($tenantUser)
        ->post(route('portal.billing.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 1_500_000,
            'payment_method' => 'transfer',
            'paid_at' => now()->toDateString(),
            'notes' => 'Paid by bank transfer.',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

    $payment = $invoice->payments()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->recorded_by)->toBe($tenantUser->id)
        ->and($payment->confirmed_by)->toBeNull()
        ->and($payment->proofs)->toHaveCount(1)
        ->and($invoice->fresh()->amount_paid)->toBe('0.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Pending);

    Storage::disk('local')->assertExists($payment->proofs->sole()->path);

    $this->actingAs($tenantUser)
        ->post(route('portal.billing.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 1,
            'payment_method' => 'transfer',
            'paid_at' => now()->toDateString(),
        ]);

    $paymentWithoutProof = $invoice->payments()->latest('id')->first();

    expect($paymentWithoutProof->status)->toBe(PaymentStatus::Pending)
        ->and($paymentWithoutProof->confirmed_by)->toBeNull();

    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('payments.verify', $payment), ['action' => 'confirm']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($invoice->fresh()->amount_paid)->toBe('1500000.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('tenant cannot submit a payment for another tenants invoice', function () {
    $tenantUser = User::factory()->create();
    Tenant::factory()->withUser($tenantUser)->create();
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);

    $this->actingAs($tenantUser)
        ->post(route('portal.billing.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 1_500_000,
            'payment_method' => 'transfer',
            'paid_at' => now()->toDateString(),
        ])
        ->assertNotFound();
});

test('tenant cannot overpay or submit to a non-payable invoice', function () {
    $tenantUser = User::factory()->create();
    $tenant = Tenant::factory()->withUser($tenantUser)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($tenantUser)
        ->post(route('portal.billing.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 1_500_001,
            'payment_method' => 'transfer',
            'paid_at' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('amount');

    Payment::factory()->pending()->create([
        'invoice_id' => $invoice->id,
        'amount' => 1_000_000,
    ]);

    $this->post(route('portal.billing.store'), [
        'invoice_id' => $invoice->id,
        'amount' => 500_001,
        'payment_method' => 'transfer',
        'paid_at' => now()->toDateString(),
    ])->assertStatus(422);

    $invoice->update([
        'amount_paid' => 1_500_000,
        'status' => InvoiceStatus::Paid,
    ]);

    $this->post(route('portal.billing.store'), [
        'invoice_id' => $invoice->id,
        'amount' => 1,
        'payment_method' => 'transfer',
        'paid_at' => now()->toDateString(),
    ])->assertSessionHasErrors('invoice_id');
});

test('tenant sees their lease unit transfer history on the reference page', function () {
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
        ->get(route('portal.lease.show', $lease))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/lease/show')
            ->has('lease.unit_histories', 1)
            ->where('lease.unit_histories.0.id', $history->id)
            ->where('lease.unit_histories.0.reason', 'maintenance'));
});

test('user without tenant profile cannot access portal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

test('user without tenant profile cannot access portal billing', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('portal.billing.invoices.show', $invoice))
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
