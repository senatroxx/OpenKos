<?php

use App\Business\Payments\PaymentAttemptStatusValidator;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\QueryException;
use OpenKOS\Core\Enums\PaymentStatus;

it('persists gateway attempts against invoices without creating canonical payments', function () {
    $invoice = Invoice::factory()->create(['total' => 1_000_000, 'amount_paid' => 0]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'currency' => 'idr',
        'metadata' => ['channel' => 'qris', 'is_test' => true],
    ]);

    expect($attempt->invoice->is($invoice))->toBeTrue()
        ->and($invoice->paymentAttempts)->toHaveCount(1)
        ->and($attempt->currency)->toBe('IDR')
        ->and($attempt->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->metadata)->toBe(['channel' => 'qris', 'is_test' => true])
        ->and($invoice->payments)->toHaveCount(0);
});

it('allows multiple attempts for one invoice', function () {
    $invoice = Invoice::factory()->create();

    PaymentAttempt::factory()->count(3)->for($invoice)->create();

    expect($invoice->paymentAttempts)->toHaveCount(3);
});

it('enforces local and provider reference uniqueness', function () {
    $invoice = Invoice::factory()->create();

    PaymentAttempt::factory()->for($invoice)->create([
        'reference' => 'attempt-1',
        'gateway_key' => 'xendit',
        'provider_reference' => 'provider-1',
    ]);

    expect(fn () => PaymentAttempt::factory()->for($invoice)->create([
        'reference' => 'attempt-1',
        'gateway_key' => 'midtrans',
        'provider_reference' => 'provider-2',
    ]))->toThrow(QueryException::class);

    expect(fn () => PaymentAttempt::factory()->for($invoice)->create([
        'reference' => 'attempt-2',
        'gateway_key' => 'xendit',
        'provider_reference' => 'provider-1',
    ]))->toThrow(QueryException::class);

    PaymentAttempt::factory()->for($invoice)->create([
        'reference' => 'attempt-3',
        'gateway_key' => 'midtrans',
        'provider_reference' => 'provider-1',
    ]);

    expect($invoice->paymentAttempts)->toHaveCount(2);
});

it('does not let gateway attempt statuses affect invoice accounting', function () {
    $invoice = Invoice::factory()->create([
        'total' => 1_000_000,
        'amount_paid' => 0,
    ]);

    PaymentAttempt::factory()->for($invoice)->create();
    PaymentAttempt::factory()->for($invoice)->failed()->create();
    PaymentAttempt::factory()->for($invoice)->expired()->create();
    PaymentAttempt::factory()->for($invoice)->canceled()->create();
    PaymentAttempt::factory()->for($invoice)->settled()->create();

    $invoice->recalculateStatus();

    expect($invoice->fresh()->amount_paid)->toBe('0.000')
        ->and($invoice->fresh()->status->value)->toBe('pending')
        ->and($invoice->payments)->toHaveCount(0);
});

it('validates the gateway attempt lifecycle', function () {
    $validator = app(PaymentAttemptStatusValidator::class);

    foreach ([PaymentStatus::Settled, PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled] as $next) {
        expect(fn () => $validator->validate(PaymentStatus::Pending, $next))->not->toThrow(Exception::class);
    }

    expect(fn () => $validator->validate(PaymentStatus::Settled, PaymentStatus::Failed))
        ->toThrow(InvalidArgumentException::class);
});

it('records the timestamp for each terminal lifecycle state', function () {
    expect(PaymentAttempt::factory()->settled()->create()->settled_at)->not->toBeNull()
        ->and(PaymentAttempt::factory()->failed()->create()->failed_at)->not->toBeNull()
        ->and(PaymentAttempt::factory()->expired()->create()->expired_at)->not->toBeNull()
        ->and(PaymentAttempt::factory()->canceled()->create()->canceled_at)->not->toBeNull();
});

it('keeps the financial snapshot immutable after creation', function () {
    $attempt = PaymentAttempt::factory()->create(['amount' => 125_000]);

    expect(fn () => $attempt->update(['amount' => 250_000]))
        ->toThrow(LogicException::class);

    expect(fn () => $attempt->update(['currency' => 'USD']))
        ->toThrow(LogicException::class);

    expect($attempt->fresh()->amount)->toBe('125000.000');
});

it('normalizes and validates currencies', function () {
    expect(PaymentAttempt::factory()->create(['currency' => 'idr'])->currency)->toBe('IDR');

    expect(fn () => PaymentAttempt::factory()->create(['currency' => 'ID']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => PaymentAttempt::factory()->create(['currency' => 'EURO']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects nested attempt metadata', function () {
    expect(fn () => PaymentAttempt::factory()->create([
        'metadata' => ['provider_payload' => ['secret' => 'value']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('links at most one attempt to a canonical payment', function () {
    $invoice = Invoice::factory()->create();
    $payment = Payment::factory()->for($invoice)->create();

    PaymentAttempt::factory()->for($invoice)->create(['payment_id' => $payment->id]);

    expect($payment->fresh()->paymentAttempt)->toBeInstanceOf(PaymentAttempt::class);

    expect(fn () => PaymentAttempt::factory()->for($invoice)->create(['payment_id' => $payment->id]))
        ->toThrow(QueryException::class);
});
