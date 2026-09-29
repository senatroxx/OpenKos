<?php

use App\Actions\Reservations\FindAvailableReservationUnits;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use App\Enums\PropertyRentalMode;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Models\Application;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Repositories\ReservationRepository;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Artisan;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function acceptedUnitApplication(Property $property, UnitType $unitType, ?User $applicant = null): Application
{
    return Application::factory()->create([
        'user_id' => ($applicant ?? User::factory()->create())->id,
        'property_id' => $property->id,
        'unit_type_id' => $unitType->id,
        'target_type' => ApplicationTargetType::UnitType,
        'status' => ApplicationStatus::Accepted,
    ]);
}

function acceptedWholePropertyApplication(Property $property, ?User $applicant = null): Application
{
    return Application::factory()->create([
        'user_id' => ($applicant ?? User::factory()->create())->id,
        'property_id' => $property->id,
        'unit_type_id' => null,
        'target_type' => ApplicationTargetType::WholeProperty,
        'status' => ApplicationStatus::Accepted,
    ]);
}

test('only accepted applicants can request a pending reservation and pending requests do not hold inventory', function () {
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $application->update(['status' => ApplicationStatus::New]);

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => now()->addWeek()->toDateString()])
        ->assertForbidden();

    $application->update(['status' => ApplicationStatus::Accepted]);
    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => now()->addWeek()->toDateString()])
        ->assertRedirect(route('applications.show', $application));

    $reservation = $application->latestReservation()->firstOrFail();

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->unit_id)->toBeNull()
        ->and($reservation->expires_at)->toBeNull()
        ->and(app(FindAvailableReservationUnits::class)->execute($application, $reservation->move_in_date->toDateString())->pluck('id')->all())->toContain($unit->id);

    $operator = User::factory()->owner()->create();

    $this->actingAs($operator)->get(route('applications.show', $application))
        ->assertInertia(fn ($page) => $page
            ->where('application.reservation.status', ReservationStatus::Pending->value)
            ->where('application.available_units.0.id', $unit->id));
});

test('legacy converted applications cannot request reservations', function (string $conversionField) {
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $tenant = Tenant::factory()->create(['user_id' => $applicant->id]);
    $application->update([
        $conversionField => $conversionField === 'converted_at' ? now() : $tenant->id,
    ]);

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => now()->addWeek()->toDateString()])
        ->assertForbidden();

    expect($application->reservations()->exists())->toBeFalse();
})->with([
    'converted timestamp' => 'converted_at',
    'converted tenant' => 'converted_tenant_id',
]);

test('unit confirmation rejects a different active lease start date', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 1,
    ]);
    $leaseEndDate = now()->addDays(3)->toDateString();
    Lease::factory()->create([
        'unit_id' => $unit->id,
        'property_id' => $property->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => $leaseEndDate,
    ]);
    $application = acceptedUnitApplication($property, $unitType);
    $moveInDate = now()->addDays(5)->toDateString();
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'move_in_date' => $moveInDate,
    ]);

    expect(app(FindAvailableReservationUnits::class)
        ->execute($application, $moveInDate)
        ->pluck('id')
        ->all())->toBe([]);

    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id])
        ->assertUnprocessable();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('unit reservation confirmation fails if the property stops supporting unit rentals', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $application = acceptedUnitApplication($property, $unitType);
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'move_in_date' => now()->addWeek()->toDateString(),
    ]);
    $property->update(['rental_mode' => PropertyRentalMode::WholeProperty]);

    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id])
        ->assertUnprocessable();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('reservation conversion does not merge into a lease ending before move-in', function () {
    $operator = User::factory()->owner()->create();
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 2,
    ]);
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'move_in_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id])
        ->assertRedirect();

    Lease::factory()->create([
        'unit_id' => $unit->id,
        'property_id' => $property->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($operator)
        ->post(route('reservations.lease.store', $reservation), [
            'start_date' => $reservation->move_in_date->toDateString(),
        ])
        ->assertUnprocessable();

    expect(Tenant::query()->where('user_id', $applicant->id)->exists())->toBeFalse()
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('unit reservations may share a unit while its remaining capacity permits', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 2,
    ]);
    $moveInDate = now()->addWeek()->toDateString();

    foreach (range(1, 3) as $index) {
        $application = acceptedUnitApplication($property, $unitType);
        $reservation = Reservation::factory()->create([
            'application_id' => $application->id,
            'move_in_date' => $moveInDate,
        ]);

        $response = $this->actingAs($operator)
            ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id]);

        if ($index < 3) {
            $response->assertRedirect();
        } else {
            $response->assertUnprocessable();
        }
    }

    expect(Reservation::query()->where('unit_id', $unit->id)->where('status', ReservationStatus::Confirmed->value)->count())->toBe(2);
});

test('available Unit selection uses shared capacity rules and ignores expired holds before scheduled expiry', function () {
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 2,
    ]);
    $moveInDate = now()->addWeek()->toDateString();
    Lease::factory()->create([
        'unit_id' => $unit->id,
        'property_id' => $property->id,
        'start_date' => $moveInDate,
        'end_date' => null,
    ]);
    $confirmedReservation = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'unit_id' => $unit->id,
        'move_in_date' => $moveInDate,
        'expires_at' => now()->addHour(),
    ]);
    $application = acceptedUnitApplication($property, $unitType);
    $availableUnits = app(FindAvailableReservationUnits::class);

    expect($availableUnits->execute($application, $moveInDate)->pluck('id')->all())->toBe([]);

    $confirmedReservation->update(['expires_at' => now()->subMinute()]);

    expect($availableUnits->execute($application, $moveInDate)->pluck('id')->all())->toContain($unit->id)
        ->and($confirmedReservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('whole-property confirmation blocks active leases even when their dates do not overlap', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $tenant = Tenant::factory()->create();
    $leaseEndDate = now()->addDays(2)->toDateString();
    Lease::factory()->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => $leaseEndDate,
    ]);
    $application = acceptedWholePropertyApplication($property);
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'move_in_date' => now()->addDays(5)->toDateString(),
    ]);

    expect(app(ReservationRepository::class)
        ->hasLeaseConflictForReservation($property, $reservation->move_in_date->toDateString()))->toBeFalse();

    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $reservation), [])
        ->assertUnprocessable();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->unit_id)->toBeNull();
});

test('lease creation counts overlapping reservation claims and keeps lease-to-lease status semantics', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $applicant = User::factory()->create();
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $reservation = Reservation::factory()->confirmed()->create([
        'application_id' => $application->id,
        'unit_id' => $unit->id,
        'move_in_date' => now()->addDays(10)->toDateString(),
    ]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($operator)
        ->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    $anotherUnit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $anotherReservation = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'unit_id' => $anotherUnit->id,
        'move_in_date' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($operator)
        ->post(route('properties.units.leases.store', [$property, $anotherUnit]), [
            'tenant_ids' => [Tenant::factory()->create()->id],
            'start_date' => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable();

    $legacyProperty = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $legacyUnit = Unit::factory()->for($legacyProperty)->create();
    $wholePropertyRate = PropertyRate::factory()->for($legacyProperty)->create();
    $wholePropertyTenant = Tenant::factory()->create();
    Lease::factory()->create([
        'property_id' => $legacyProperty->id,
        'unit_id' => $legacyUnit->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($operator)
        ->post(route('properties.leases.store', $legacyProperty), [
            'tenant_ids' => [$wholePropertyTenant->id],
            'property_rate_id' => $wholePropertyRate->id,
            'start_date' => now()->addDays(20)->toDateString(),
        ])
        ->assertUnprocessable();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($anotherReservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('confirmed reservations block conflicting hybrid lease targets', function () {
    $operator = User::factory()->owner()->create();

    $propertyReservationProperty = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $propertyReservationUnitType = UnitType::factory()->for($propertyReservationProperty)->create();
    $propertyReservationUnit = Unit::factory()->for($propertyReservationProperty)->create([
        'unit_type_id' => $propertyReservationUnitType->id,
    ]);
    $propertyReservation = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedWholePropertyApplication($propertyReservationProperty)->id,
    ]);

    $this->actingAs($operator)
        ->post(route('properties.units.leases.store', [$propertyReservationProperty, $propertyReservationUnit]), [
            'tenant_ids' => [Tenant::factory()->create()->id],
            'start_date' => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable();

    $unitReservationProperty = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $unitReservationUnitType = UnitType::factory()->for($unitReservationProperty)->create();
    $unitReservationUnit = Unit::factory()->for($unitReservationProperty)->create([
        'unit_type_id' => $unitReservationUnitType->id,
    ]);
    $unitReservation = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($unitReservationProperty, $unitReservationUnitType)->id,
        'unit_id' => $unitReservationUnit->id,
    ]);
    $propertyRate = PropertyRate::factory()->for($unitReservationProperty)->create();

    $this->actingAs($operator)
        ->post(route('properties.leases.store', $unitReservationProperty), [
            'tenant_ids' => [Tenant::factory()->create()->id],
            'property_rate_id' => $propertyRate->id,
            'start_date' => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable();

    expect($propertyReservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($unitReservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('confirmed holds protect their unit and target from inventory edits', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $unitType = UnitType::factory()->for($property)->create();
    $otherUnitType = UnitType::factory()->for($property)->create(['name' => 'Loft']);
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 2,
    ]);
    Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'unit_id' => $unit->id,
    ]);

    $payload = fn (): array => [
        'name' => $unit->name,
        'capacity' => $unit->capacity,
        'updated_at' => $unit->fresh()->updated_at->toISOString(),
    ];

    $this->actingAs($operator)
        ->put(route('properties.units.update', [$property, $unit]), [
            ...$payload(),
            'unit_type_id' => $otherUnitType->id,
        ])
        ->assertSessionHasErrors('unit_type_id');

    $this->actingAs($operator)
        ->put(route('properties.units.update', [$property, $unit]), [
            ...$payload(),
            'status' => UnitStatus::Maintenance->value,
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($operator)
        ->put(route('properties.units.update', [$property, $unit]), [
            ...$payload(),
            'capacity' => 0,
        ])
        ->assertSessionHasErrors('capacity');

    $this->actingAs($operator)
        ->delete(route('properties.units.destroy', [$property, $unit]))
        ->assertRedirect();

    expect($unit->fresh()->unit_type_id)->toBe($unitType->id)
        ->and($unit->fresh()->status)->toBe(UnitStatus::Available)
        ->and($unit->fresh()->capacity)->toBe(2)
        ->and($unit->fresh()->deleted_at)->toBeNull();
});

test('confirmed property and unit reservations prevent incompatible rental model edits and archiving', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Hybrid]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($operator)
        ->put(route('properties.update', $property), [
            'name' => $property->name,
            'rental_mode' => PropertyRentalMode::WholeProperty->value,
        ])
        ->assertSessionHasErrors('rental_mode');

    $propertyReservation = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedWholePropertyApplication($property)->id,
    ]);

    $this->actingAs($operator)
        ->delete(route('properties.destroy', $property))
        ->assertRedirect();

    expect($propertyReservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($property->fresh()->is_active)->toBeTrue()
        ->and($property->fresh()->rental_mode)->toBe(PropertyRentalMode::Hybrid);
});

test('creating a lease from a confirmed reservation creates the tenant and converts both records atomically', function () {
    $operator = User::factory()->owner()->create();
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $reservation = Reservation::factory()->confirmed()->create([
        'application_id' => $application->id,
        'unit_id' => $unit->id,
        'move_in_date' => now()->addWeek()->toDateString(),
    ]);

    $this->actingAs($operator)
        ->post(route('reservations.lease.store', $reservation), [
            'start_date' => $reservation->move_in_date->toDateString(),
        ])
        ->assertRedirect();

    $reservation->refresh();
    $application->refresh();
    $tenant = Tenant::query()->where('user_id', $applicant->id)->firstOrFail();
    $lease = Lease::query()->findOrFail($reservation->lease_id);

    expect($reservation->status)->toBe(ReservationStatus::Converted)
        ->and($reservation->lease_id)->toBe($lease->id)
        ->and($application->converted_tenant_id)->toBe($tenant->id)
        ->and($lease->tenants()->whereKey($tenant->id)->exists())->toBeTrue()
        ->and($lease->unit_id)->toBe($unit->id);
});

test('multiple reservations with the same unit lease start date can convert to one shared lease', function () {
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 3,
    ]);
    $existingTenant = Tenant::factory()->create();
    $moveInDate = now()->addWeek()->toDateString();
    $lease = Lease::factory()->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'primary_tenant_id' => $existingTenant->id,
        'start_date' => $moveInDate,
        'end_date' => null,
    ]);
    $reservations = collect(range(1, 2))->map(fn (): Reservation => Reservation::factory()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'move_in_date' => $moveInDate,
    ]));

    foreach ($reservations as $reservation) {
        $this->actingAs($operator)
            ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id])
            ->assertRedirect();
    }

    foreach ($reservations as $reservation) {
        $this->actingAs($operator)
            ->post(route('reservations.lease.store', $reservation), [
                'start_date' => $moveInDate,
            ])
            ->assertRedirect();
    }

    expect($reservations->every(fn (Reservation $reservation): bool => $reservation->refresh()->status === ReservationStatus::Converted && $reservation->lease_id === $lease->id))->toBeTrue()
        ->and($lease->fresh()->tenants)->toHaveCount(3);
});

test('lease creation failure leaves the confirmed reservation and applicant unconverted', function () {
    $operator = User::factory()->owner()->create();
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $reservation = Reservation::factory()->confirmed()->create([
        'application_id' => $application->id,
        'unit_id' => $unit->id,
        'move_in_date' => now()->addWeek()->toDateString(),
    ]);
    $unit->update(['status' => UnitStatus::Maintenance]);

    $this->actingAs($operator)
        ->post(route('reservations.lease.store', $reservation), [
            'start_date' => $reservation->move_in_date->toDateString(),
        ])
        ->assertUnprocessable();

    expect(Tenant::query()->where('user_id', $applicant->id)->exists())->toBeFalse()
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($application->fresh()->converted_tenant_id)->toBeNull();
});

test('expiry releases a confirmed hold and terminal reservations allow another request', function (ReservationStatus $terminalStatus) {
    $applicant = User::factory()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'status' => $terminalStatus,
        'move_in_date' => now()->addWeek()->toDateString(),
    ]);

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => now()->addWeek()->toDateString()])
        ->assertRedirect();

    expect($application->reservations()->count())->toBe(2)
        ->and($reservation->refresh()->status)->toBe($terminalStatus);
})->with([
    'cancelled' => ReservationStatus::Cancelled,
    'rejected' => ReservationStatus::Rejected,
    'expired' => ReservationStatus::Expired,
]);

test('the scheduled expiry command releases due confirmed holds', function () {
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $application = acceptedUnitApplication($property, $unitType);
    $expired = Reservation::factory()->confirmed()->create([
        'application_id' => $application->id,
        'expires_at' => now()->subMinute(),
    ]);
    $active = Reservation::factory()->confirmed()->create([
        'application_id' => acceptedUnitApplication($property, $unitType)->id,
        'expires_at' => now()->addHour(),
    ]);

    Artisan::call('reservations:expire');

    expect($expired->refresh()->status)->toBe(ReservationStatus::Expired)
        ->and($expired->expired_at)->not->toBeNull()
        ->and($active->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('applicants may cancel pending and confirmed reservations and reclaim the same application', function () {
    $applicant = User::factory()->create();
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 1,
    ]);
    $application = acceptedUnitApplication($property, $unitType, $applicant);
    $moveInDate = now()->addWeek()->toDateString();

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => $moveInDate])
        ->assertRedirect();

    $pendingReservation = $application->latestReservation()->firstOrFail();
    $this->actingAs($applicant)
        ->patch(route('reservations.cancel', $pendingReservation))
        ->assertRedirect();
    expect($pendingReservation->refresh()->status)->toBe(ReservationStatus::Cancelled);

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => $moveInDate])
        ->assertRedirect();

    $confirmedReservation = $application->latestReservation()->firstOrFail();
    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $confirmedReservation), ['unit_id' => $unit->id])
        ->assertRedirect();
    $this->actingAs($applicant)
        ->patch(route('reservations.cancel', $confirmedReservation))
        ->assertRedirect();

    $this->actingAs($applicant)
        ->post(route('applications.reservations.store', $application), ['move_in_date' => $moveInDate])
        ->assertRedirect();

    $retry = $application->latestReservation()->firstOrFail();
    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $retry), ['unit_id' => $unit->id])
        ->assertRedirect();

    expect($retry->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($application->reservations()->count())->toBe(3);
});

test('hold duration is configurable and starts only on confirmation', function () {
    Setting::set('reservation_hold_hours', 12);
    $operator = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $application = acceptedUnitApplication($property, $unitType);
    $reservation = Reservation::factory()->create([
        'application_id' => $application->id,
        'move_in_date' => now()->addWeek()->toDateString(),
    ]);

    expect($reservation->expires_at)->toBeNull();

    $this->actingAs($operator)
        ->patch(route('reservations.confirm', $reservation), ['unit_id' => $unit->id])
        ->assertRedirect();

    $reservation->refresh();
    expect($reservation->confirmed_at->diffInHours($reservation->expires_at))->toBe(12.0);
});
