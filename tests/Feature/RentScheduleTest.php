<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createScheduleLease(array $overrides = [])
{
    $property = Property::factory()->create();
    $room = Room::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();

    return Lease::factory()->create(array_merge([
        'room_id' => $room->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => '2026-05-24',
        'rent_amount' => 1_500_000,
        'rent_due_day' => 1,
        'status' => 'active',
        'end_date' => null,
    ], $overrides));
}

it('generates schedule from first due date after lease start', function () {
    $lease = createScheduleLease(['start_date' => '2026-05-24', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    $schedule = $lease->schedule(months: 3);

    expect($schedule[0]->period_start->format('Y-m-d'))->toBe('2026-06-01');
    expect($schedule[0]->due_date->format('Y-m-d'))->toBe('2026-06-01');
    expect($schedule[0]->amount)->toBe('1500000.00');
});

it('includes start month when due day is on or before start date', function () {
    $lease = createScheduleLease(['start_date' => '2026-05-01', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    $schedule = $lease->schedule(months: 2);

    expect($schedule[0]->period_start->format('Y-m-d'))->toBe('2026-05-01');
});

it('clamps due day to month length', function () {
    $lease = createScheduleLease(['start_date' => '2026-01-15', 'rent_due_day' => 31]);

    Carbon::setTestNow('2026-04-01');

    $schedule = $lease->schedule(months: 2);

    expect($schedule[1]->due_date->format('Y-m-d'))->toBe('2026-02-28');
});

it('clamps due day to 29 in February on leap year', function () {
    $lease = createScheduleLease(['start_date' => '2024-01-15', 'rent_due_day' => 31]);

    Carbon::setTestNow('2024-04-01');

    $schedule = $lease->schedule(months: 2);

    expect($schedule[1]->due_date->format('Y-m-d'))->toBe('2024-02-29');
});

it('marks paid when non-cancelled payment exists', function () {
    $lease = createScheduleLease(['start_date' => '2026-05-24', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    Payment::factory()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'confirmed',
    ]);

    $schedule = $lease->schedule(months: 3);

    expect($schedule[0]->status)->toBe('paid');
    expect($schedule[1]->status)->toBe('upcoming');
});

it('marks overdue for past due dates with no payment', function () {
    $lease = createScheduleLease(['start_date' => '2026-03-15', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    $schedule = $lease->schedule(months: 0);

    expect($schedule[0]->status)->toBe('overdue');
});

it('marks upcoming for future months', function () {
    $lease = createScheduleLease(['start_date' => '2026-04-15', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-05-01');

    $schedule = $lease->schedule(months: 2);

    expect($schedule[1]->status)->toBe('upcoming');
});

it('marks due for current month with no payment', function () {
    $lease = createScheduleLease(['start_date' => '2026-04-15', 'rent_due_day' => 28]);

    Carbon::setTestNow('2026-06-01');

    $schedule = $lease->schedule(months: 0);

    expect($schedule->last()->status)->toBe('due');
});

it('stops schedule at lease end date', function () {
    $lease = createScheduleLease([
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-15',
        'rent_due_day' => 1,
    ]);

    Carbon::setTestNow('2026-01-15');

    $schedule = $lease->schedule(months: 3);

    expect($schedule)->toHaveCount(3);
    expect($schedule[0]->period_start->format('Y-m'))->toBe('2026-01');
    expect($schedule[2]->period_start->format('Y-m'))->toBe('2026-03');
});

it('pending payment marks period as paid', function () {
    $lease = createScheduleLease(['start_date' => '2026-05-24', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    Payment::factory()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'pending',
    ]);

    $schedule = $lease->schedule(months: 3);

    expect($schedule[0]->status)->toBe('paid');
});

it('returns empty collection when rent_amount is missing', function () {
    $lease = createScheduleLease(['rent_amount' => null]);

    expect($lease->schedule())->toBeEmpty();
});

it('applies correct period boundaries', function () {
    $lease = createScheduleLease(['start_date' => '2026-06-15', 'rent_due_day' => 5]);

    Carbon::setTestNow('2026-06-27');

    $schedule = $lease->schedule(months: 2);

    expect($schedule[0]->period_start->format('Y-m-d'))->toBe('2026-07-01');
    expect($schedule[0]->period_end->format('Y-m-d'))->toBe('2026-07-31');
    expect($schedule[0]->due_date->format('Y-m-d'))->toBe('2026-07-05');
});

it('authorizes schedule view for owner on property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $user->properties()->attach($property);

    $room = Room::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();
    $lease = Lease::factory()->create([
        'room_id' => $room->id,
        'primary_tenant_id' => $tenant->id,
        'start_date' => '2026-05-24',
        'rent_amount' => 1_500_000,
        'rent_due_day' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->getJson("/leases/{$lease->id}/rent-schedule");

    $response->assertOk();
    $response->assertJsonStructure(['schedule']);
});

it('denies schedule view for user not on property', function () {
    $user = User::factory()->admin()->create();
    $lease = createScheduleLease();

    $this->actingAs($user);

    $this->getJson("/leases/{$lease->id}/rent-schedule")->assertForbidden();
});

it('generates one entry per year for yearly billing', function () {
    $lease = createScheduleLease([
        'start_date' => '2026-01-15',
        'rent_due_day' => 1,
        'billing_unit' => 'year',
        'billing_interval' => 1,
    ]);

    Carbon::setTestNow('2026-06-27');

    $schedule = $lease->schedule(months: 36);

    expect($schedule)->toHaveCount(3);
    expect($schedule[0]->period_start->format('Y-m-d'))->toBe('2027-01-01');
    expect($schedule[0]->period_end->format('Y-m-d'))->toBe('2027-12-31');
});

it('clamps period and due date to lease end date', function () {
    $lease = createScheduleLease([
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-15',
        'rent_due_day' => 25,
    ]);

    Carbon::setTestNow('2026-01-15');

    $schedule = $lease->schedule(months: 12);

    expect($schedule)->toHaveCount(3);
    expect($schedule[2]->period_end->format('Y-m-d'))->toBe('2026-03-15');
    expect($schedule[2]->due_date->format('Y-m-d'))->toBe('2026-03-15');
});

it('allows owner to view schedule without property assignment', function () {
    $user = User::factory()->owner()->create();
    $lease = createScheduleLease();

    $this->actingAs($user);

    $this->getJson("/leases/{$lease->id}/rent-schedule")->assertOk();
});

it('shows paid badge with pending payment', function () {
    $lease = createScheduleLease(['start_date' => '2026-05-24', 'rent_due_day' => 1]);

    Carbon::setTestNow('2026-06-27');

    Payment::factory()->create([
        'paymentable_id' => $lease->id,
        'paymentable_type' => Lease::class,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => 'pending',
    ]);

    $schedule = $lease->schedule(months: 3);

    expect($schedule[0]->status)->toBe('paid');
});
