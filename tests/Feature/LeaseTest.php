<?php

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\MoveOutLease;
use App\Data\Lease\CreateLeaseData;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createPropertyWithUnit(): array
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1_000_000)->create([
        'property_id' => $property->id,
    ]);

    return [$property, $unit];
}

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        [$property, $unit] = createPropertyWithUnit();

        $this->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertRedirect('login');
    });

    it('returns 403 for users without leases.view permission', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertForbidden();
    });

    it('allows admin to access leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->admin()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk();
    });

    it('allows owner to access leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk();
    });

    it('denies staff access to leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists lease history for a unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        Lease::factory()->terminated()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/leases/index')
                ->has('leases', 2)
            );
    });

    it('creates a lease and assigns tenant to unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
            'rent_amount' => 1_500_000,
            'billing_unit' => 'month',
            'billing_interval' => 1,
            'deposit_amount' => 1_000_000,
            'rent_due_day' => 5,
        ]);

        $lease = Lease::first();

        expect($lease)->not->toBeNull();
        expect($lease->primary_tenant_id)->toBe($tenant->id);
        expect($lease->unit_id)->toBe($unit->id);
        expect($lease->rent_amount)->toBe('1500000.000');
        expect($lease->deposit_amount)->toBe('1000000.000');
        expect($lease->rent_due_day)->toBe(5);
        expect($lease->status)->toBe(LeaseStatus::Active);
    });

    it('uses unit base price when rent amount is not specified', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
        ]);

        $lease = Lease::first();

        expect($lease->rent_amount)->toBe($unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount'));
    });

    it('uses the selected unit rate when creating a lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();
        $rate = $unit->rates()->firstOrFail();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
                'unit_rate_id' => $rate->id,
            ])
            ->assertRedirect();

        $lease = Lease::firstOrFail();

        expect($lease->unit_rate_id)->toBe($rate->id)
            ->and($lease->rent_amount)->toBe($rate->amount);
    });

    it('snapshots the selected currency-specific rate', function () {
        [$property, $unit] = createPropertyWithUnit();
        $usdRate = $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => '95.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
                'unit_rate_id' => $usdRate->id,
            ])
            ->assertRedirect();

        expect(Lease::firstOrFail()->currency)->toBe('USD')
            ->and(Lease::firstOrFail()->rent_amount)->toBe('95.000');
    });

    it('inherits the configured currency from the default rate', function () {
        [$property, $unit] = createPropertyWithUnit();
        Setting::set('currency', 'USD');
        $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => '95.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
            ])
            ->assertRedirect();

        expect(Lease::firstOrFail()->currency)->toBe('USD')
            ->and(Lease::firstOrFail()->rent_amount)->toBe('95.000');
    });

    it('rejects a rate from another unit when creating a lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $otherUnit = Unit::factory()->withRate(1_250_000)->create([
            'property_id' => $property->id,
        ]);
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
                'unit_rate_id' => $otherUnit->rates()->firstOrFail()->id,
            ])
            ->assertSessionHasErrors('unit_rate_id');

        expect(Lease::query()->exists())->toBeFalse();
    });

    it('rejects a stale rate when creating a lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
                'unit_rate_id' => $unit->rates()->max('id') + 1,
            ])
            ->assertSessionHasErrors('unit_rate_id');

        expect(Lease::query()->exists())->toBeFalse();
    });

    it('rejects invalid rates in the lease creation action', function () {
        [, $unit] = createPropertyWithUnit();
        $otherUnit = Unit::factory()->withRate(1_250_000)->create();
        $tenant = Tenant::factory()->create();

        foreach ([$otherUnit->rates()->firstOrFail()->id, $unit->rates()->max('id') + 1] as $unitRateId) {
            $data = new CreateLeaseData(
                tenantIds: [$tenant->id],
                startDate: '2026-06-01',
                endDate: null,
                rentAmount: null,
                billingInterval: null,
                billingUnit: null,
                billingStrategy: null,
                unitRateId: $unitRateId,
                depositAmount: null,
                depositPaidAt: null,
                depositRefundAmount: null,
                depositRefundedAt: null,
                rentDueDay: null,
                notes: null,
            );

            $this->assertThrows(
                fn () => app(CreateLease::class)->execute($unit, $data),
                fn (HttpException $exception): bool => $exception->getStatusCode() === 422,
            );
        }

        expect(Lease::query()->exists())->toBeFalse();
    });

    it('validates required fields on create', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [])
            ->assertSessionHasErrors(['tenant_ids', 'start_date']);
    });

    it('prevents assigning tenant to an already occupied unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Lease::factory()->create([
            'primary_tenant_id' => $tenantA->id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenantB->id],
                'start_date' => '2026-06-01',
            ])
            ->assertStatus(422);
    });

    it('prevents assigning a tenant to a second active lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $otherUnit = Unit::factory()->for($property)->create();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $otherUnit]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
            ])
            ->assertStatus(422);
    });

    it('updates a lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'rent_amount' => 1_000_000,
        ]);

        $this->actingAs($user)
            ->put(route('properties.units.leases.update', [$property, $unit, $lease]), [
                'rent_amount' => 2_000_000,
            ])
            ->assertForbidden();
    });
});

describe('termination', function () {
    it('terminates an active lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'end_date' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('properties.units.leases.destroy', [$property, $unit, $lease]));

        $lease->refresh();

        expect($lease->status)->toBe(LeaseStatus::Terminated);
        expect($lease->end_date)->not->toBeNull();
        expect($lease->termination_date)->not->toBeNull();
    });
});

describe('move unit', function () {
    it('moves a tenant to a new unit', function () {
        [$property, $unitA] = createPropertyWithUnit();
        $unitB = Unit::factory()->withRate(1_200_000)->create([
            'property_id' => $property->id,
        ]);

        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
            'rent_amount' => 1_000_000,
            'deposit_amount' => 500_000,
            'deposit_paid_at' => now(),
            'rent_due_day' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.move', [$property, $unitA, $lease]), [
                'target_unit_id' => $unitB->id,
            ]);

        $lease->refresh();

        expect($lease->status)->toBe(LeaseStatus::Terminated);
        expect($lease->end_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));

        $newLease = Lease::where('unit_id', $unitB->id)->first();

        expect($newLease)->not->toBeNull();
        expect($newLease->primary_tenant_id)->toBe($tenant->id);
        expect($newLease->status)->toBe(LeaseStatus::Active);
        expect($newLease->rent_amount)->toBe('1000000.000');
        expect($newLease->deposit_amount)->toBe('500000.000');
    });

    it('returns authoritative transition state from a move', function () {
        $property = Property::factory()->create();
        $targetUnit = Unit::factory()->withRate(1_200_000)->for($property)->create();
        $sourceUnit = Unit::factory()->withRate(1_000_000)->for($property)->occupied()->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $sourceUnit->id,
            'status' => LeaseStatus::Active,
        ]);

        $result = app(MoveOutLease::class)->execute($lease, new MoveOutLeaseData(
            terminationDate: now()->toDateString(),
            endDate: now()->toDateString(),
            reason: 'Moved to target unit',
            moveToAnotherUnit: true,
            targetUnitId: $targetUnit->id,
        ));

        expect($result->oldLeaseStatus)->toBe(LeaseStatus::Active)
            ->and($result->oldSourceStatus)->toBe(UnitStatus::Occupied)
            ->and($result->newSourceStatus)->toBe(UnitStatus::Available)
            ->and($result->oldTargetStatus)->toBe(UnitStatus::Available)
            ->and($result->newTargetStatus)->toBe(UnitStatus::Occupied);
    });

    it('rejects moving a lease to its current unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => LeaseStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.move', [$property, $unit, $lease]), [
                'target_unit_id' => $unit->id,
            ])
            ->assertSessionHasErrors('target_unit_id');

        expect($lease->fresh()->status)->toBe(LeaseStatus::Active);
    });

    it('denies admin from accessing leases of a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->get(route('properties.units.leases.index', [$propertyB, $unitB]))
            ->assertForbidden();
    });

    it('denies admin from creating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->post(route('properties.units.leases.store', [$propertyB, $unitB]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
            ])
            ->assertForbidden();
    });

    it('denies admin from updating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
        ]);

        $this->actingAs($admin)
            ->put(route('properties.units.leases.update', [$propertyB, $unitB, $lease]), [
                'rent_amount' => 9_999_999,
            ])
            ->assertForbidden();
    });

    it('denies admin from terminating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('properties.units.leases.destroy', [$propertyB, $unitB, $lease]))
            ->assertForbidden();
    });

    it('denies admin from moving a lease to a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('properties.units.leases.move', [$propertyB, $unitB, $lease]), [
                'target_unit_id' => Unit::factory()->for($propertyB)->create()->id,
            ])
            ->assertForbidden();
    });

    it('denies admin from moving a lease to a target unit in a different property', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA, $unitA] = createPropertyWithUnit();
        [$propertyB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
        ]);
        $targetUnitInB = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->post(route('properties.units.leases.move', [$propertyA, $unitA, $lease]), [
                'target_unit_id' => $targetUnitInB->id,
            ])
            ->assertForbidden();
    });

    it('prevents moving to an already occupied unit', function () {
        [$property, $unitA] = createPropertyWithUnit();
        $unitB = Unit::factory()->create([
            'property_id' => $property->id,
        ]);

        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $leaseA = Lease::factory()->create([
            'primary_tenant_id' => $tenantA->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
        ]);

        Lease::factory()->create([
            'primary_tenant_id' => $tenantB->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.move', [$property, $unitA, $leaseA]), [
                'target_unit_id' => $unitB->id,
            ])
            ->assertStatus(422);
    });

    it('locks reverse-direction move units in ascending id order', function () {
        $property = Property::factory()->create();
        $targetUnit = Unit::factory()->withRate(1_200_000)->for($property)->create();
        $sourceUnit = Unit::factory()->withRate(1_000_000)->for($property)->create();
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $sourceUnit->id,
            'status' => LeaseStatus::Active,
        ]);

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        app(MoveOutLease::class)->execute($lease, new MoveOutLeaseData(
            terminationDate: now()->toDateString(),
            endDate: now()->toDateString(),
            reason: 'Moved to target unit',
            moveToAnotherUnit: true,
            targetUnitId: $targetUnit->id,
        ));

        $lockQueries = collect(DB::connection()->getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "units"') && str_contains($query['query'], 'order by "id" asc'));

        expect($lockQueries)->not->toBeEmpty();

        $lockQuery = $lockQueries->last();

        if ($lockQuery['bindings'] !== []) {
            expect(array_map('intval', $lockQuery['bindings']))->toBe([$targetUnit->id, $sourceUnit->id]);
        } else {
            expect($lockQuery['query'])->toContain(sprintf('in (%d, %d)', $targetUnit->id, $sourceUnit->id));
        }
    });
});

describe('unit occupancy derived from lease', function () {
    it('shows unit as occupied after lease creation', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
        ]);

        $unit->refresh();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/index')
                ->has('units.data', 1)
                ->where('units.data.0.active_leases', 1)
            );
    });
});
