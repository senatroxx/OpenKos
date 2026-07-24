<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createPropertyWithUnits(array $unitConfigs): Property
{
    $property = Property::factory()->create();

    foreach ($unitConfigs as $config) {
        $factory = Unit::factory();

        $state = $config['state'] ?? 'available';
        if ($state !== 'available') {
            $factory = $factory->{$state}();
        }

        $factory->create(['property_id' => $property->id]);
    }

    return $property;
}

test('dashboard returns occupancy stats matching seeded data', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    // 6 units total: 3 occupied, 2 available, 1 maintenance
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 6)
            ->where('stats.occupied_units', 3)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 1)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 50)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.name', $property->name)
            ->where('stats.properties.0.total_units', 6)
            ->where('stats.properties.0.occupied_units', 3)
            ->where('stats.properties.0.available_units', 2)
            ->where('stats.properties.0.maintenance_units', 1)
            ->where('stats.properties.0.unavailable_units', 0)
            ->where('stats.properties.0.occupancy_percentage', 50)
        );
});

test('dashboard counts occupied units from unit status, not lease presence', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
    ]);

    // Unit marked occupied even without a lease — still counted as occupied
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 3)
            ->where('stats.occupied_units', 1)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 33)
            ->where('stats.properties.0.occupied_units', 1)
            ->where('stats.properties.0.available_units', 2)
        );
});

test('dashboard returns correct stats for multiple properties', function () {
    $user = User::factory()->owner()->create();

    createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'available'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 6)
            ->where('stats.occupied_units', 3)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 1)
            ->where('stats.unavailable_units', 0)
            ->has('stats.properties', 2)
        );
});

test('dashboard returns zero stats when no properties exist', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 0)
            ->where('stats.occupied_units', 0)
            ->where('stats.available_units', 0)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 0)
            ->where('stats.properties', [])
        );
});

test('dashboard does not count unavailable units as available', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'unavailable'],
        ['state' => 'unavailable'],
    ]);

    // 5 units: 1 occupied, 2 available, 2 unavailable
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 5)
            ->where('stats.occupied_units', 1)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 2)
            ->where('stats.occupancy_percentage', 20)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.total_units', 5)
            ->where('stats.properties.0.occupied_units', 1)
            ->where('stats.properties.0.available_units', 2)
            ->where('stats.properties.0.maintenance_units', 0)
            ->where('stats.properties.0.unavailable_units', 2)
            ->where('stats.properties.0.occupancy_percentage', 20)
        );
});

test('dashboard requires authentication', function () {
    $this->get(route('dashboard'))->assertRedirect('login');
});

test('dashboard requires dashboard.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('dashboard shows overdue invoice count and amount in attention', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease = Lease::factory()->create(['status' => LeaseStatus::Active->value, 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease->id,
        'due_date' => '2026-07-03',
        'total' => 500000,
        'amount_paid' => 100000,
        'status' => InvoiceStatus::Partial,
    ]);

    $lease2 = Lease::factory()->create(['status' => LeaseStatus::Active->value, 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease2->id,
        'due_date' => '2026-07-05',
        'total' => 200000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.overdue_invoices.count', 2)
            ->where('attention.overdue_invoices.amount', 600000)
        );

    Carbon::setTestNow();
});

test('dashboard shows due today count in attention', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $lease = Lease::factory()->create(['status' => LeaseStatus::Active->value, 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create([
        'lease_id' => $lease->id,
        'due_date' => '2026-07-10',
        'total' => 300000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.due_today', 1)
        );

    Carbon::setTestNow();
});

test('dashboard shows open maintenance count in attention', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    MaintenanceTicket::factory()->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'status' => 'reported',
    ]);
    MaintenanceTicket::factory()->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'status' => 'in_progress',
    ]);
    MaintenanceTicket::factory()->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'status' => 'resolved',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.open_maintenance', 2)
        );
});

test('dashboard shows leases ending soon count in attention', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    Lease::factory()->create([
        'status' => LeaseStatus::Active->value,
        'start_date' => now()->subMonths(6),
        'end_date' => '2026-07-20',
    ]);
    Lease::factory()->create([
        'status' => LeaseStatus::Active->value,
        'start_date' => now()->subMonths(6),
        'end_date' => '2026-08-05',
    ]);
    Lease::factory()->create([
        'status' => LeaseStatus::Active->value,
        'start_date' => now()->subMonths(6),
        'end_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.leases_ending_soon', 2)
        );

    Carbon::setTestNow();
});

test('dashboard shows pending payment verification count in attention', function () {
    $user = User::factory()->owner()->create();

    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    Payment::factory()->pending()->create(['invoice_id' => $invoice->id]);
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => PaymentStatus::Confirmed,
    ]);

    $otherLease = Lease::factory()->create();
    $otherInvoice = Invoice::factory()->create(['lease_id' => $otherLease->id]);
    Payment::factory()->pending()->create(['invoice_id' => $otherInvoice->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.pending_payment_verification', 2)
        );
});

test('dashboard scopes pending payment verification count to accessible properties', function () {
    $user = User::factory()->admin()->create();

    $accessibleProperty = Property::factory()->create();
    $user->properties()->sync([$accessibleProperty->id]);
    $accessibleLease = Lease::factory()->create([
        'unit_id' => Unit::factory()->for($accessibleProperty)->create()->id,
    ]);
    $accessibleInvoice = Invoice::factory()->create(['lease_id' => $accessibleLease->id]);
    Payment::factory()->pending()->create(['invoice_id' => $accessibleInvoice->id]);

    $hiddenLease = Lease::factory()->create();
    $hiddenInvoice = Invoice::factory()->create(['lease_id' => $hiddenLease->id]);
    Payment::factory()->pending()->create(['invoice_id' => $hiddenInvoice->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.pending_payment_verification', 1)
        );
});

test('dashboard includes recent activity from audit log', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-10'));

    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recent_activity')
        );

    Carbon::setTestNow();
});

test('dashboard attention shows zeros when no actionable items exist', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attention.overdue_invoices.count', 0)
            ->where('attention.overdue_invoices.amount', 0)
            ->where('attention.due_today', 0)
            ->where('attention.open_maintenance', 0)
            ->where('attention.leases_ending_soon', 0)
            ->where('attention.pending_payment_verification', 0)
        );
});

test('dashboard returns properties and units for maintenance sheet', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create();
    Unit::factory()->create(['property_id' => $property->id, 'status' => 'available']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('properties')
            ->has('units')
        );
});
