<?php

use App\Actions\Payments\StartGatewayPayment;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\PaymentGatewayCreationException;
use App\Exceptions\PaymentGatewayCurrencyUnsupportedException;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Core\Data\Payment\CheckoutInstruction;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Enums\PaymentStatus;
use Spatie\Permission\Models\Permission as SpatiePermission;

function startGatewayPaymentAction(PaymentGateway $gateway): StartGatewayPayment
{
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('active')->andReturn($gateway);
    $manager->shouldReceive('activeKey')->andReturn('test-gateway');
    $manager->shouldReceive('supportsCurrency')->zeroOrMoreTimes()->andReturn(null);

    app()->instance(PaymentGatewayManager::class, $manager);

    return app(StartGatewayPayment::class);
}

function paymentGatewayResult(PaymentRequest $request, ?Money $amount = null): PaymentCreationResult
{
    return new PaymentCreationResult(
        providerReference: 'provider-reference',
        status: PaymentStatus::Pending,
        amount: $amount ?? $request->amount,
        instructions: new CheckoutInstructions(
            url: 'https://example.test/checkout',
            entries: [new CheckoutInstruction('va_number', '1234567890', 'VA number')],
        ),
        metadata: ['channel' => 'virtual_account'],
    );
}

it('creates a provider attempt with exact currency-aware money and normalized instructions', function () {
    Setting::set('currency', 'IDR');
    $invoice = Invoice::factory()->create([
        'total' => 1_500_000,
        'amount_paid' => 0,
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->with(Mockery::on(function (PaymentRequest $request): bool {
            return $request->amount->minorUnits === 1_500_000
                && $request->amount->currency === 'IDR'
                && $request->metadata['invoice_id'] !== null;
        }))
        ->andReturnUsing(fn (PaymentRequest $request) => paymentGatewayResult($request));

    $result = startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create());

    expect($result->reused)->toBeFalse()
        ->and($result->instructions->url)->toBe('https://example.test/checkout')
        ->and($result->instructions->entries[0]->value)->toBe('1234567890')
        ->and($result->attempt->fresh()->amount)->toBe('1500000.000')
        ->and($result->attempt->fresh()->currency)->toBe('IDR')
        ->and($result->attempt->fresh()->gateway_key)->toBe('test-gateway')
        ->and($result->attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($result->attempt->fresh()->checkout_instructions['entries'][0]['key'])->toBe('va_number')
        ->and($result->attempt->fresh()->metadata['channel'])->toBe('virtual_account');
});

it('rejects an unsupported invoice currency before creating an attempt', function () {
    $invoice = Invoice::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldNotReceive('createPayment');

    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('active')->once()->andReturn($gateway);
    $manager->shouldReceive('activeKey')->once()->andReturn('test-gateway');
    $manager->shouldReceive('supportsCurrency')
        ->once()
        ->with($gateway, $invoice->currency)
        ->andReturnFalse();
    app()->instance(PaymentGatewayManager::class, $manager);

    expect(fn () => app(StartGatewayPayment::class)->execute(
        $invoice,
        User::factory()->owner()->create(),
    ))->toThrow(PaymentGatewayCurrencyUnsupportedException::class, 'not available');

    expect($invoice->paymentAttempts()->count())->toBe(0);
});

it('reuses a valid pending attempt without creating another provider checkout', function () {
    $invoice = Invoice::factory()->create();
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => 'existing-provider-reference',
        'expires_at' => now()->addHour(),
        'checkout_instructions' => [
            'url' => 'https://example.test/existing',
            'entries' => [],
        ],
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldNotReceive('createPayment');

    $result = startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create());

    expect($result->reused)->toBeTrue()
        ->and($result->attempt->is($attempt))->toBeTrue()
        ->and($result->instructions->url)->toBe('https://example.test/existing');
});

it('reuses pending instructions even when no gateway is currently active', function () {
    $invoice = Invoice::factory()->create();
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => 'existing-provider-reference',
        'expires_at' => now()->addHour(),
        'checkout_instructions' => [
            'url' => 'https://example.test/existing',
            'entries' => [],
        ],
    ]);
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldNotReceive('active');
    app()->instance(PaymentGatewayManager::class, $manager);

    $result = app(StartGatewayPayment::class)->execute($invoice, User::factory()->owner()->create());

    expect($result->reused)->toBeTrue()
        ->and($result->attempt->is($attempt))->toBeTrue();
});

it('does not reuse an attempt while provider creation is unresolved', function () {
    $invoice = Invoice::factory()->create();
    PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => null,
        'expires_at' => null,
        'checkout_instructions' => null,
        'metadata' => ['provider_creation_state' => 'in_progress'],
    ]);
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldNotReceive('active');
    app()->instance(PaymentGatewayManager::class, $manager);

    expect(fn () => app(StartGatewayPayment::class)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(PaymentGatewayCreationException::class);
});

it('supersedes stale provider creation attempts without references', function (string $creationState) {
    $invoice = Invoice::factory()->create();
    $orphaned = PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => null,
        'expires_at' => null,
        'checkout_instructions' => null,
        'metadata' => ['provider_creation_state' => $creationState],
        'updated_at' => now()->subMinutes(6),
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request) => paymentGatewayResult($request));

    $result = startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create());

    expect($result->reused)->toBeFalse()
        ->and($orphaned->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($orphaned->fresh()->metadata['provider_creation_state'])->toBe('superseded')
        ->and($invoice->paymentAttempts()->count())->toBe(2);
})->with(['in_progress', 'uncertain']);

it('expires stale pending attempts before creating a replacement', function () {
    $invoice = Invoice::factory()->create();
    $stale = PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => null,
        'expires_at' => now()->subMinute(),
        'checkout_instructions' => null,
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request) => paymentGatewayResult($request));

    $result = startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create());

    expect($stale->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($result->attempt->id)->not->toBe($stale->id)
        ->and($invoice->paymentAttempts()->count())->toBe(2);
});

it('rechecks stale provider sessions before expiring them', function () {
    $invoice = Invoice::factory()->create();
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'provider_reference' => 'existing-provider-reference',
        'expires_at' => now()->subMinute(),
        'checkout_instructions' => [
            'url' => 'https://example.test/existing',
            'entries' => [],
        ],
    ]);
    $gateway = Mockery::mock(PaymentGateway::class, PaymentGatewayStatusLookup::class);
    $gateway->shouldReceive('lookupPaymentStatus')
        ->once()
        ->andReturn(new PaymentProviderResult(
            providerReference: $attempt->provider_reference,
            status: PaymentStatus::Pending,
            reference: $attempt->reference,
            amount: new Money((int) $attempt->amount, $attempt->currency),
        ));
    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('find')->with('test-gateway')->zeroOrMoreTimes()->andReturn($gateway);
    $manager->shouldReceive('supportsStatusLookup')->with('test-gateway')->once()->andReturnTrue();
    $manager->shouldNotReceive('active');
    app()->instance(PaymentGatewayManager::class, $manager);

    $result = app(StartGatewayPayment::class)->execute($invoice, User::factory()->owner()->create());

    expect($result->reused)->toBeTrue()
        ->and($result->attempt->id)->toBe($attempt->id)
        ->and($attempt->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('keeps ambiguous provider failures pending for reconciliation', function () {
    $invoice = Invoice::factory()->create();
    Log::spy();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andThrow(new RuntimeException('timeout from provider SDK'));

    expect(fn () => startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(PaymentGatewayCreationException::class);

    $attempt = $invoice->paymentAttempts()->sole();
    expect($attempt->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->metadata['provider_creation_state'])->toBe('uncertain')
        ->and($attempt->metadata['provider_creation_uncertain'])->toBeTrue();

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Payment gateway checkout creation failed.', Mockery::on(
            fn (array $context): bool => $context['invoice_id'] === $invoice->id
                && $context['attempt_id'] === $attempt->id
                && $context['gateway_key'] === 'test-gateway'
                && $context['exception_class'] === RuntimeException::class,
        ));
});

it('fails closed when the provider returns a different amount or currency', function () {
    $invoice = Invoice::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request) => paymentGatewayResult($request, new Money(1, 'USD')));

    expect(fn () => startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(PaymentGatewayCreationException::class);

    expect($invoice->paymentAttempts()->sole()->status)->toBe(PaymentStatus::Failed);
});

it('fails closed when the provider returns no usable checkout instructions', function () {
    $invoice = Invoice::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request) => new PaymentCreationResult(
            providerReference: 'provider-reference',
            status: PaymentStatus::Pending,
            amount: $request->amount,
            instructions: new CheckoutInstructions,
        ));

    expect(fn () => startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(PaymentGatewayCreationException::class);

    expect($invoice->paymentAttempts()->sole()->status)->toBe(PaymentStatus::Failed);
});

it('keeps terminal provider creation responses pending for confirmation', function () {
    $invoice = Invoice::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturnUsing(fn (PaymentRequest $request) => new PaymentCreationResult(
            providerReference: 'provider-reference',
            status: PaymentStatus::Settled,
            amount: $request->amount,
            instructions: new CheckoutInstructions,
        ));

    expect(fn () => startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(PaymentGatewayCreationException::class);

    $attempt = $invoice->paymentAttempts()->sole();
    expect($attempt->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->provider_reference)->toBe('provider-reference')
        ->and($attempt->metadata['provider_creation_state'])->toBe('uncertain')
        ->and(Payment::query()->count())->toBe(0);
});

it('blocks a new checkout after a settled attempt', function () {
    $invoice = Invoice::factory()->create();
    PaymentAttempt::factory()->for($invoice)->settled()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('key')->andReturn('test-gateway');
    $gateway->shouldNotReceive('createPayment');

    expect(fn () => startGatewayPaymentAction($gateway)->execute($invoice, User::factory()->owner()->create()))
        ->toThrow(InvoiceNotPayableException::class);
});

it('denies payment when the invoice lease is no longer available', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(SpatiePermission::findOrCreate('payments.create'));
    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->for($lease)->create();
    $lease->delete();

    expect(Gate::forUser($user)->allows('pay', $invoice))->toBeFalse();
});
