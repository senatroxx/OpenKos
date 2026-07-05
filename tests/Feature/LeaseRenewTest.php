<?php

use App\Data\Lease\RenewLeaseData;
use App\Enums\DepositHandling;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createRenewableLease(array $overrides = []): array
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $tenant = Tenant::factory()->create();

    $lease = Lease::factory()->create(array_merge([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'rent_amount' => 1_000_000,
        'status' => 'active',
        'deposit_amount' => 500_000,
        'deposit_paid_at' => now(),
        'rent_due_day' => 5,
    ], $overrides));

    return [$property, $unit, $lease, $tenant];
}

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        [, $unit, $lease] = createRenewableLease();

        $this->post(route('leases.renew', $lease))
            ->assertRedirect('login');
    });

    it('returns 403 for users without leases.renew permission', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease))
            ->assertForbidden();
    });

    it('allows owner to renew a lease', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertRedirect(route('leases.index'));
    });

    it('allows admin with property access to renew', function () {
        [$property, , $lease] = createRenewableLease();
        $user = User::factory()->admin()->create();
        $user->givePermissionTo('leases.renew');
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertRedirect(route('leases.index'));
    });

    it('denies admin without renew permission', function () {
        [$property, , $lease] = createRenewableLease();
        $user = User::factory()->admin()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertForbidden();
    });

    it('denies admin without property access', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->admin()->create();
        $user->givePermissionTo('leases.renew');

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertForbidden();
    });
});

describe('eligibility', function () {
    it('rejects open-ended leases', function () {
        [, , $lease] = createRenewableLease(['end_date' => null]);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);
        $lease->refresh();

        expect($lease->status)->toBe('active');
    });

    it('rejects terminated leases', function () {
        [, , $lease] = createRenewableLease(['status' => 'terminated']);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertSessionHasNoErrors();
        $lease->refresh();

        expect($lease->status)->toBe('terminated');
    });

    it('rejects expired leases', function () {
        [, , $lease] = createRenewableLease(['status' => 'expired']);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);
        $lease->refresh();

        expect($lease->status)->toBe('expired');
    });
});

describe('renewal', function () {
    it('creates a new lease with updated rent and extension', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_500_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertRedirect(route('leases.index'));

        $lease->refresh();

        expect($lease->status)->toBe('renewed');

        $newLease = $lease->fresh()->renewedLease;

        expect($newLease)->not->toBeNull();
        expect($newLease->previous_lease_id)->toBe($lease->id);
        expect($newLease->status)->toBe('active');
        expect($newLease->rent_amount)->toBe('1500000.00');
        expect($newLease->start_date->format('Y-m-d'))->toBe('2026-07-01');
        expect($newLease->end_date->format('Y-m-d'))->toBe('2027-06-30');
        expect($newLease->unit_id)->toBe($lease->unit_id);
    });

    it('preserves tenants on the new lease', function () {
        [, , $lease, $tenant] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 6,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = $lease->fresh()->renewedLease;

        expect($newLease->tenants->pluck('id')->toArray())->toContain($tenant->id);
        expect($newLease->primary_tenant_id)->toBe($tenant->id);
    });

    it('sets previous_lease_id on the new lease', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = Lease::where('previous_lease_id', $lease->id)->first();

        expect($newLease)->not->toBeNull();
        expect($newLease->previous_lease_id)->toBe($lease->id);
    });

    it('carries forward deposit to new lease', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = $lease->fresh()->renewedLease;

        expect($newLease->deposit_amount)->toBe('500000.00');
        expect($newLease->deposit_paid_at)->not->toBeNull();
    });

    it('computes end date correctly for years extension', function () {
        [, , $lease] = createRenewableLease(['end_date' => '2026-12-31']);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_000_000,
                'extension_value' => 2,
                'extension_unit' => 'years',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = $lease->fresh()->renewedLease;

        expect($newLease->start_date->format('Y-m-d'))->toBe('2027-01-01');
        expect($newLease->end_date->format('Y-m-d'))->toBe('2028-12-31');
    });

    it('copies billing terms from old lease', function () {
        [, , $lease] = createRenewableLease([
            'billing_interval' => 3,
            'billing_unit' => 'month',
            'rent_due_day' => 15,
        ]);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = $lease->fresh()->renewedLease;

        expect($newLease->billing_interval)->toBe(3);
        expect($newLease->billing_unit->value)->toBe('month');
        expect($newLease->rent_due_day)->toBe(15);
    });

    it('does not move payments from old lease', function () {
        [, , $lease] = createRenewableLease();
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ]);

        $newLease = $lease->fresh()->renewedLease;

        expect($lease->payments()->count())->toBe(1);
        expect($newLease->payments()->count())->toBe(0);
    });
});

describe('outstanding balance', function () {
    it('allows renewal with no outstanding balance', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 6,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertRedirect(route('leases.index'));

        $lease->refresh();

        expect($lease->status)->toBe('renewed');
    });

    it('blocks renewal with outstanding balance without confirmation', function () {
        [, , $lease] = createRenewableLease();
        $lease->payments()->create([
            'amount' => 500_000,
            'payment_date' => now(),
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);
        $user = User::factory()->owner()->create();

        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 6,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
            ])
            ->assertSessionHasNoErrors();
        $lease->refresh();

        expect($lease->status)->toBe('active');
    });

    it('allows renewal with paid lease without confirmation', function () {
        [, , $lease] = createRenewableLease();
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $lease->payments()->create([
            'amount' => 1_000_000,
            'payment_date' => now(),
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 6,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
            ])
            ->assertRedirect(route('leases.index'));

        $lease->refresh();

        expect($lease->status)->toBe('renewed');
    });

    it('allows renewal with outstanding balance when confirmed', function () {
        [, , $lease] = createRenewableLease();
        $lease->payments()->create([
            'amount' => 500_000,
            'payment_date' => now(),
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);
        $user = User::factory()->owner()->create();

        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_200_000,
                'extension_value' => 6,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertRedirect(route('leases.index'));

        $lease->refresh();

        expect($lease->status)->toBe('renewed');
    });
});

describe('validation', function () {
    it('validates required fields', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [])
            ->assertSessionHasErrors(['rent_amount', 'extension_value', 'extension_unit', 'deposit_handling']);
    });

    it('validates rent_amount is integer', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 'abc',
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertSessionHasErrors(['rent_amount']);
    });

    it('validates extension_value range', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_000_000,
                'extension_value' => 0,
                'extension_unit' => 'months',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertSessionHasErrors(['extension_value']);
    });

    it('validates deposit_handling values', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_000_000,
                'extension_value' => 12,
                'extension_unit' => 'months',
                'deposit_handling' => 'invalid',
            ])
            ->assertSessionHasErrors(['deposit_handling']);
    });

    it('validates extension_unit values', function () {
        [, , $lease] = createRenewableLease();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('leases.renew', $lease), [
                'rent_amount' => 1_000_000,
                'extension_value' => 12,
                'extension_unit' => 'days',
                'deposit_handling' => 'carry_forward',
                'confirmed_outstanding' => true,
            ])
            ->assertSessionHasErrors(['extension_unit']);
    });
});

describe('RenewLeaseData', function () {
    it('can be instantiated', function () {
        $data = new RenewLeaseData(
            endDate: now()->addYear(),
            rentAmount: 1_200_000,
            depositHandling: DepositHandling::CarryForward,
            confirmedOutstanding: true,
        );

        expect($data->rentAmount)->toBe(1_200_000);
        expect($data->confirmedOutstanding)->toBeTrue();
    });
});
