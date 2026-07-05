<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createLeaseForProperty(): Lease
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();

    return Lease::factory()->create([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
    ]);
}

describe('authorization', function () {
    it('allows owner to view payment', function () {
        $user = User::factory()->owner()->create();
        $payment = Payment::factory()->create();

        expect($user->can('view', $payment))->toBeTrue();
    });

    it('allows admin assigned to the property to view payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create(['unit_id' => $unit->id, 'primary_tenant_id' => $tenant->id]);
        $payment = Payment::factory()->create([
            'paymentable_id' => $lease->id,
            'paymentable_type' => Lease::class,
        ]);

        expect($admin->can('view', $payment))->toBeTrue();
    });

    it('denies admin not assigned to the property from viewing payment', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create(['unit_id' => $unit->id, 'primary_tenant_id' => $tenant->id]);
        $payment = Payment::factory()->create([
            'paymentable_id' => $lease->id,
            'paymentable_type' => Lease::class,
        ]);

        expect($admin->can('view', $payment))->toBeFalse();
    });
});

describe('payment recording', function () {
    it('allows owner to record payment for active lease', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
                'notes' => 'Test payment',
            ]);

        expect($lease->fresh()->payments)->toHaveCount(1)
            ->and($lease->fresh()->payments->first()->status)->toBe('confirmed');
    });

    it('allows admin assigned to property to record payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'transfer',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ]);

        expect($lease->fresh()->payments)->toHaveCount(1)
            ->and($lease->fresh()->payments->first()->confirmedBy->id)->toBe($admin->id);
    });

    it('denies admin not assigned to property from recording payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ])
            ->assertForbidden();
    });

    it('prevents duplicate payment for same lease and billing period', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ]);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ])
            ->assertSessionHasErrors('period');
    });

    it('prevents recording payment for inactive lease', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $lease->update(['status' => 'terminated']);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ])
            ->assertSessionHasErrors('lease');
    });

    it('requires positive amount', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 0,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ])
            ->assertSessionHasErrors('amount');
    });

    it('sets recorded_by and confirmed_by to the recording user', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), [
                'amount' => 1_500_000,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
                'period_month' => now()->month,
                'period_year' => now()->year,
            ]);

        $payment = $lease->fresh()->payments->first();

        expect((int) $payment->recorded_by)->toBe((int) $user->id);
        expect((int) $payment->confirmed_by)->toBe((int) $user->id);
        expect($payment->status)->toBe('confirmed');
    });
});
