<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SignedInvoicePaymentLink;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\URL;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstruction;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Enums\PaymentStatus as GatewayPaymentStatus;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function bindSignedPaymentGateway(?PaymentGateway $gateway, ?bool $currencySupport = null): void
{
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('activeKey')->andReturn($gateway ? 'test-gateway' : null);
    $manager->shouldReceive('active')->andReturn($gateway);
    $manager->shouldReceive('supportsCurrency')->zeroOrMoreTimes()->andReturn($currencySupport);

    app()->instance(PaymentGatewayManager::class, $manager);
}

function signedInvoiceUrl(Invoice $invoice): string
{
    return app(SignedInvoicePaymentLink::class)->url($invoice);
}

function signedPaymentGatewayResult(PaymentRequest $request): PaymentCreationResult
{
    return new PaymentCreationResult(
        providerReference: 'provider-reference',
        status: GatewayPaymentStatus::Pending,
        amount: $request->amount,
        instructions: new CheckoutInstructions(
            url: 'https://example.test/checkout',
            entries: [new CheckoutInstruction('reference', $request->reference)],
        ),
    );
}

it('shows an opaque signed payment link in the invoice workspace', function () {
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);

    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('leases.workspace.invoices.show', [$lease, $invoice]));

    $response->assertInertia(fn ($page) => $page
        ->where('paymentLink', fn (string $url): bool => ! str_contains(parse_url($url, PHP_URL_PATH) ?: '', "/{$invoice->id}")
            && str_contains(parse_url($url, PHP_URL_QUERY) ?: '', 'signature=')));
});

it('renders a signed invoice page without exposing lease data', function () {
    $invoice = Invoice::factory()->create();
    $invoice->lease->primaryTenant->update(['name' => 'Budi Santoso']);
    $url = signedInvoiceUrl($invoice);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payments/signed-invoice')
            ->where('invoice.reference', $invoice->reference)
            ->where('invoice.status', 'pending')
            ->where('invoice.context.tenant_name', 'B*** S***')
            ->where('invoice.context.property_name', $invoice->lease->unit->property->name)
            ->where('invoice.context.unit_name', $invoice->lease->unit->name)
            ->missing('invoice.id')
            ->missing('invoice.lease_id')
            ->missing('invoice.context.tenant_email')
            ->where('paymentUrl', $url));
});

it('ignores a browser status query parameter without trusting it', function () {
    $invoice = Invoice::factory()->create();

    $this->get(signedInvoiceUrl($invoice).'&status=success')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.status', 'pending')
            ->where('onlinePaymentAvailable', false));
});

it('rejects unsigned numeric invoice paths', function () {
    $invoice = Invoice::factory()->create();

    $this->get(route('payments.signed.show', ['token' => $invoice->id]))
        ->assertForbidden();
});

it('rejects a signed token that cannot resolve to an invoice', function () {
    $url = URL::signedRoute('payments.signed.show', ['token' => 'not-a-real-token']);

    $this->get($url)->assertNotFound();
});

it('starts checkout from a signed link without authentication', function () {
    $invoice = Invoice::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request): PaymentCreationResult => signedPaymentGatewayResult($request));
    bindSignedPaymentGateway($gateway);

    $this->post(signedInvoiceUrl($invoice))
        ->assertRedirect('https://example.test/checkout');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Pending)
        ->and($invoice->paymentAttempts()->sole()->status)->toBe(GatewayPaymentStatus::Pending);
});

it('hides signed checkout when the gateway rejects the invoice currency', function () {
    $invoice = Invoice::factory()->create(['currency' => 'USD']);
    bindSignedPaymentGateway(Mockery::mock(PaymentGateway::class), currencySupport: false);

    $this->get(signedInvoiceUrl($invoice))
        ->assertInertia(fn ($page) => $page
            ->where('onlinePaymentAvailable', false));
});

it('rejects unsupported signed checkout without creating an attempt', function () {
    $invoice = Invoice::factory()->create(['currency' => 'USD']);
    bindSignedPaymentGateway(Mockery::mock(PaymentGateway::class), currencySupport: false);

    $this->post(signedInvoiceUrl($invoice))
        ->assertRedirect()
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Online payment is not available for this invoice.',
        ]);

    expect($invoice->paymentAttempts()->count())->toBe(0);
});

it('reuses persisted checkout instructions when no gateway is active', function () {
    $invoice = Invoice::factory()->create();
    $url = signedInvoiceUrl($invoice);
    PaymentAttempt::factory()->for($invoice)->create([
        'expires_at' => now()->addHour(),
        'checkout_instructions' => [
            'url' => 'https://example.test/resume',
            'entries' => [],
        ],
    ]);
    $manager = Mockery::mock(PaymentGatewayManager::class);
    app()->instance(PaymentGatewayManager::class, $manager);

    $this->get($url)
        ->assertInertia(fn ($page) => $page
            ->where('onlinePaymentAvailable', true)
            ->where('gatewayAttempts.0.resumable', true));

    $this->post($url)
        ->assertRedirect('https://example.test/resume');

    expect($invoice->paymentAttempts()->count())->toBe(1);
});

it('does not start checkout for a paid invoice', function () {
    $invoice = Invoice::factory()->paid()->create();
    $url = signedInvoiceUrl($invoice);
    bindSignedPaymentGateway(null);

    $this->post($url)
        ->assertRedirect($url)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Online payment is not available for this invoice.',
        ]);

    expect($invoice->paymentAttempts()->count())->toBe(0);
});
