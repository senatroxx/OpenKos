<?php

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfArtifact;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Models\User;
use App\Services\Invoices\InvoicePdfArtifact;
use App\Services\Payments\PaymentGatewayManager;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Enums\PaymentStatus as GatewayPaymentStatus;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
    $this->owner = User::factory()->owner()->create();
    Setting::set('invoice_pdf_enabled', true, 'boolean');
    Storage::fake('local');
});

function prepareWorkspaceInvoicePdf(Invoice $invoice): void
{
    $artifact = app(InvoicePdfArtifact::class);

    GenerateInvoicePdfArtifact::dispatchSync(
        $invoice->getKey(),
        $artifact->fingerprint($invoice),
    );
}

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
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'confirmed_by' => $this->owner->id,
        ]);
        PaymentProof::factory()->create(['payment_id' => $payment->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('leases/invoice-detail')
                ->where('lease.id', $lease->id)
                ->where('invoice.id', $invoice->id)
                ->has('invoice.line_items', 1)
                ->has('invoice.payments', 1)
                ->has('gatewayAttempts', 0)
                ->where('invoice.payments.0.confirmed_by.name', $this->owner->name)
                ->has('invoice.payments.0.proofs', 1));
    });

    it('shows normalized gateway attempts without exposing provider metadata', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
        $attempt = PaymentAttempt::factory()->for($invoice)->create([
            'gateway_key' => 'xendit',
            'reference' => 'attempt-1',
            'provider_reference' => 'provider-1',
            'metadata' => [
                'provider_status' => 'ACTIVE',
                'secret' => 'must-not-be-exposed',
            ],
        ]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('gatewayAttempts.0.id', $attempt->id)
                ->where('gatewayAttempts.0.gateway', 'xendit')
                ->where('gatewayAttempts.0.reference', 'attempt-1')
                ->where('gatewayAttempts.0.provider_reference', 'provider-1')
                ->where('gatewayAttempts.0.status', 'pending')
                ->where('gatewayAttempts.0.recheckable', true)
                ->missing('gatewayAttempts.0.metadata'));
    });

    it('rechecks a gateway attempt from invoice detail', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create([
            'lease_id' => $lease->id,
            'total' => 1_500_000,
            'amount_paid' => 0,
        ]);
        $attempt = PaymentAttempt::factory()->for($invoice)->create([
            'gateway_key' => 'test-gateway',
            'reference' => 'attempt-1',
            'provider_reference' => 'provider-1',
            'amount' => 1_500_000,
            'currency' => 'IDR',
        ]);
        $gateway = Mockery::mock(PaymentGateway::class, PaymentGatewayStatusLookup::class);
        $gateway->shouldReceive('lookupPaymentStatus')->once()->andReturn(new PaymentProviderResult(
            providerReference: 'provider-1',
            status: GatewayPaymentStatus::Settled,
            reference: 'attempt-1',
            amount: new Money(1_500_000, 'IDR'),
        ));
        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('find')->with('test-gateway')->andReturn($gateway);
        app()->instance(PaymentGatewayManager::class, $manager);

        $this->from(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->actingAs($this->owner)
            ->post(route('leases.workspace.invoices.payment-attempts.recheck', [$lease, $invoice, $attempt]))
            ->assertRedirect(route('leases.workspace.invoices.show', [$lease, $invoice]));

        expect($attempt->fresh()->status)->toBe(GatewayPaymentStatus::Settled)
            ->and($invoice->fresh()->amount_paid)->toBe('1500000.000');
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

    it('queues and exposes invoice PDF status', function () {
        Bus::fake();
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertInertia(fn ($page) => $page->where('invoicePdf.status', 'pending'));

        Bus::assertDispatched(GenerateInvoicePdfArtifact::class);
    });

    it('downloads a generated invoice PDF', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
        prepareWorkspaceInvoicePdf($invoice);

        $response = $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.download', [$lease, $invoice]));

        $response
            ->assertOk()
            ->assertStreamed()
            ->assertDownload("invoice-{$invoice->reference}.pdf")
            ->assertHeader('content-type', 'application/pdf');
    });

    it('opens a print-ready invoice PDF page', function () {
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.print', [$lease, $invoice]))
            ->assertOk()
            ->assertSee($invoice->reference)
            ->assertSee('window.print()');
    });

    it('uses print fallback when invoice PDF generation is disabled', function () {
        Setting::set('invoice_pdf_enabled', false, 'boolean');
        Bus::fake();
        $lease = Lease::factory()->create();
        $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);

        $this->actingAs($this->owner)
            ->get(route('leases.workspace.invoices.show', [$lease, $invoice]))
            ->assertInertia(fn ($page) => $page->where('invoicePdf.status', 'disabled'));

        Bus::assertNotDispatched(GenerateInvoicePdfArtifact::class);
    });
});
