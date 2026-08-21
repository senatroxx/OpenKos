<?php

use App\Actions\Invoices\AllocatePayment;
use App\Actions\Payments\ApplyGatewayPaymentResult;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus as ApplicationPaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Events\PaymentRecorded as PlatformPaymentRecorded;
use OpenKOS\Core\Exceptions\PaymentWebhookPayloadException;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

function bindWebhookGateway(PaymentWebhookResult|Throwable $callback): void
{
    $gateway = Mockery::mock(PaymentGateway::class);

    if ($callback instanceof Throwable) {
        $gateway->shouldReceive('handleCallback')->andThrow($callback);
    } else {
        $gateway->shouldReceive('handleCallback')->andReturn($callback);
    }

    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('find')->with('test/gateway')->andReturn($gateway);

    app()->instance(PaymentGatewayManager::class, $manager);
}

function gatewayWebhookResult(
    PaymentStatus $status,
    string $providerReference = 'provider-1',
    ?string $reference = null,
    ?Money $amount = null,
): PaymentWebhookResult {
    return new PaymentWebhookResult(
        eventReference: 'event-'.$providerReference,
        providerReference: $providerReference,
        status: $status,
        reference: $reference,
        amount: $amount,
    );
}

it('settles an attempt and creates exactly one canonical payment', function () {
    Event::fake([
        PaymentRecorded::class,
        PlatformPaymentRecorded::class,
    ]);

    $invoice = Invoice::factory()->create([
        'total' => 1_500_000,
        'amount_paid' => 0,
    ]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => null,
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
        'currency' => 'IDR',
    ]);
    $result = gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    );
    bindWebhookGateway($result);

    $response = $this->postJson('/api/webhooks/payment/test/gateway', ['ignored' => true]);

    $payment = Payment::query()->sole();
    $attempt->refresh();
    $invoice->refresh();

    $response->assertOk()->assertJson(['status' => 'processed']);
    expect($attempt->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->provider_reference)->toBe('provider-1')
        ->and($attempt->payment_id)->toBe($payment->id)
        ->and($payment->status)->toBe(ApplicationPaymentStatus::Confirmed)
        ->and($payment->payment_method)->toBe('gateway')
        ->and($payment->amount)->toBe('1500000.000')
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe('1500000.000')
        ->and($payment->allocations)->toHaveCount(1);

    Event::assertDispatched(PaymentRecorded::class);
    Event::assertDispatched(PlatformPaymentRecorded::class, fn (PlatformPaymentRecorded $event): bool => $event->paymentId === $payment->id);
});

it('acknowledges duplicate settlement without creating another payment', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    $result = gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    );

    bindWebhookGateway($result);
    $this->postJson('/api/webhooks/payment/test/gateway', [])->assertOk();

    bindWebhookGateway($result);
    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertOk()
        ->assertJson(['status' => 'duplicate']);

    expect(Payment::query()->count())->toBe(1)
        ->and($attempt->fresh()->payment_id)->not->toBeNull();
});

it('reports an expired callback after a settled attempt as an anomaly', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $payment = Payment::factory()->for($invoice)->create([
        'amount' => 1_500_000,
        'status' => ApplicationPaymentStatus::Confirmed,
    ]);
    $attempt = PaymentAttempt::factory()->for($invoice)->settled()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'payment_id' => $payment->id,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Expired,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->fresh()->payment_id)->toBe($payment->id);
    Log::shouldHaveReceived('warning')->once();
});

it('requires provider and OpenKOS references to identify the same attempt', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 3_000_000]);
    $first = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-2',
        'reference' => 'attempt-2',
        'amount' => 1_500_000,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        providerReference: 'provider-1',
        reference: 'attempt-2',
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($first->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});

it('applies failed callbacks without affecting invoice accounting', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => null,
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Failed,
        reference: $attempt->reference,
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($attempt->fresh()->provider_reference)->toBe('provider-1')
        ->and(Payment::query()->count())->toBe(0)
        ->and($invoice->fresh()->amount_paid)->toBe('0.000');
});

it('applies a late settlement after a failed local attempt', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->failed()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->fresh()->payment_id)->not->toBeNull()
        ->and(Payment::query()->count())->toBe(1);
});

it('applies a late settlement after local expiry', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->expired()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->fresh()->payment_id)->not->toBeNull()
        ->and(Payment::query()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('fails closed for a settled attempt without a linked payment', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->settled()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect(Payment::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});

it('reports a non-settled attempt with a linked payment as an anomaly', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $payment = Payment::factory()->for($invoice)->create(['amount' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->failed()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'payment_id' => $payment->id,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Failed,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($attempt->fresh()->payment_id)->toBe($payment->id);
    Log::shouldHaveReceived('warning')->once();
});

it('reports a settled attempt linked to another invoice as an anomaly', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $otherInvoice = Invoice::factory()->create(['total' => 1_500_000]);
    $payment = Payment::factory()->for($otherInvoice)->create(['amount' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->settled()->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'payment_id' => $payment->id,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->fresh()->payment_id)->toBe($payment->id);
    Log::shouldHaveReceived('warning')->once();
});

it('rejects callback amount or currency mismatches without settling', function () {
    Log::spy();
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
        'currency' => 'IDR',
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(150_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
});

it('does not settle an attempt when the invoice no longer has enough balance', function () {
    Log::spy();
    $invoice = Invoice::factory()->create([
        'total' => 1_500_000,
        'amount_paid' => 1_500_000,
        'status' => InvoiceStatus::Paid,
    ]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'anomaly']);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
});

it('acknowledges unknown callbacks without creating records', function () {
    Log::spy();
    bindWebhookGateway(gatewayWebhookResult(
        PaymentStatus::Settled,
        reference: 'missing-attempt',
        amount: new Money(1_500_000, 'IDR'),
    ));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'unknown']);

    expect(PaymentAttempt::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('returns unauthorized for invalid webhook verification', function () {
    bindWebhookGateway(new PaymentWebhookVerificationException('secret details must not escape'));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertUnauthorized()
        ->assertJson(['message' => 'Webhook verification failed.'])
        ->assertJsonMissing(['message' => 'secret details must not escape']);
});

it('acknowledges authenticated malformed webhook payloads without exposing details', function () {
    bindWebhookGateway(new PaymentWebhookPayloadException('raw payload details must not escape'));

    $this->postJson('/api/webhooks/payment/test/gateway', [])
        ->assertStatus(202)
        ->assertJson(['status' => 'invalid_payload'])
        ->assertJsonMissing(['message' => 'raw payload details must not escape']);
});

it('rolls back the payment and attempt when invoice allocation fails', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test/gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    $allocator = Mockery::mock(AllocatePayment::class);
    $allocator->shouldReceive('execute')->once()->andThrow(new RuntimeException('allocation failed'));
    app()->instance(AllocatePayment::class, $allocator);

    expect(fn () => app(ApplyGatewayPaymentResult::class)->execute(
        'test/gateway',
        gatewayWebhookResult(
            PaymentStatus::Settled,
            reference: $attempt->reference,
            amount: new Money(1_500_000, 'IDR'),
        ),
    ))->toThrow(RuntimeException::class, 'allocation failed');

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->fresh()->payment_id)->toBeNull()
        ->and(Payment::query()->count())->toBe(0)
        ->and($invoice->fresh()->amount_paid)->toBe('0.000');
});
