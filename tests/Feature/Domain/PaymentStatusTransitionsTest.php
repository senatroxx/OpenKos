<?php

use App\Business\Payments\PaymentStatusValidator;
use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

it('validates pending to confirmed transition', function () {
    $validator = app(PaymentStatusValidator::class);

    expect(fn () => $validator->validate(PaymentStatus::Pending, PaymentStatus::Confirmed))->not->toThrow(Exception::class);
});

it('validates pending to cancelled transition', function () {
    $validator = app(PaymentStatusValidator::class);

    expect(fn () => $validator->validate(PaymentStatus::Pending, PaymentStatus::Cancelled))->not->toThrow(Exception::class);
});

it('rejects confirmed to any transition', function () {
    $validator = app(PaymentStatusValidator::class);

    expect(fn () => $validator->validate(PaymentStatus::Confirmed, PaymentStatus::Pending))->toThrow(Exception::class);
    expect(fn () => $validator->validate(PaymentStatus::Confirmed, PaymentStatus::Cancelled))->toThrow(Exception::class);
});

it('rejects cancelled to any transition', function () {
    $validator = app(PaymentStatusValidator::class);

    expect(fn () => $validator->validate(PaymentStatus::Cancelled, PaymentStatus::Pending))->toThrow(Exception::class);
    expect(fn () => $validator->validate(PaymentStatus::Cancelled, PaymentStatus::Confirmed))->toThrow(Exception::class);
});

it('transitions payment from pending to confirmed via verify', function () {
    $lease = Lease::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
    ]);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->post(route('payments.verify', $payment), ['action' => 'confirm']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('transitions payment from pending to cancelled via reject', function () {
    $lease = Lease::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
    ]);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->post(route('payments.verify', $payment), ['action' => 'reject']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('rejects verify on already confirmed payment', function () {
    $lease = Lease::factory()->create();
    $payment = Payment::factory()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
        'status' => PaymentStatus::Confirmed,
    ]);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->post(route('payments.verify', $payment), ['action' => 'confirm'])
        ->assertStatus(422);
});
