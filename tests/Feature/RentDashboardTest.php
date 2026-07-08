<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function createLeaseWithPayment(array $leaseOverrides = [], ?array $paymentData = null): Lease
{
    $lease = Lease::factory()->create(array_merge([
        'start_date' => now()->subMonths(2),
        'rent_amount' => 100000,
        'rent_due_day' => 5,
    ], $leaseOverrides));

    if ($paymentData !== null) {
        Invoice::factory()->create([
            'lease_id' => $lease->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_date' => now()->startOfMonth()->addDays(4),
            'total' => 100000,
            'amount_paid' => 100000,
            'status' => InvoiceStatus::Paid,
        ]);
    }

    return $lease;
}

test('rent dashboard loads with stats for owner', function () {
    Carbon::setTestNow(now()->setDay(10));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(
        ['rent_due_day' => 5],
        ['status' => 'confirmed'],
    );

    createLeaseWithPayment(['rent_due_day' => 5]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data')
            ->has('stats')
        );

    Carbon::setTestNow();
});

test('rent dashboard shows correct overdue count', function () {
    Carbon::setTestNow(now()->setDay(10));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(['start_date' => now()->subMonths(2), 'rent_due_day' => 5]);
    createLeaseWithPayment(['start_date' => now()->subMonths(2), 'rent_due_day' => 3]);
    createLeaseWithPayment(
        ['start_date' => now()->subMonths(2), 'rent_due_day' => 5],
        ['status' => 'confirmed'],
    );

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.overdue.count', 2)
            ->where('stats.overdue.amount', 200000)
            ->where('stats.paid', 1)
        );

    Carbon::setTestNow();
});

test('rent dashboard shows due today correctly', function () {
    $today = now()->day;
    Carbon::setTestNow(now()->setDay($today));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(['rent_due_day' => $today]);
    createLeaseWithPayment(['rent_due_day' => $today - 1]);
    createLeaseWithPayment(
        ['rent_due_day' => $today],
        ['status' => 'confirmed'],
    );

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.due_today', 1)
            ->where('stats.overdue.count', 1)
            ->where('stats.paid', 1)
        );

    Carbon::setTestNow();
});

test('rent dashboard shows due soon correctly', function () {
    $today = now()->day;
    Carbon::setTestNow(now()->setDay($today));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(['rent_due_day' => $today + 3]);
    createLeaseWithPayment(['rent_due_day' => $today + 5]);
    createLeaseWithPayment(['rent_due_day' => $today + 14]);
    createLeaseWithPayment(
        ['rent_due_day' => $today + 3],
        ['status' => 'confirmed'],
    );

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.due_soon', 2)
        );

    Carbon::setTestNow();
});

test('rent dashboard status filter works', function () {
    Carbon::setTestNow(now()->setDay(10));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(['rent_due_day' => 5]);
    createLeaseWithPayment(
        ['rent_due_day' => 5],
        ['status' => 'confirmed'],
    );

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['status' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
        );

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['status' => 'paid']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
        );

    Carbon::setTestNow();
});

test('rent dashboard search filters by tenant name', function () {
    Carbon::setTestNow(now()->setDay(10));

    $user = User::factory()->owner()->create();

    $tenantA = Tenant::factory()->create(['name' => 'Budi Santoso']);
    Lease::factory()->create([
        'primary_tenant_id' => $tenantA->id,
        'start_date' => now()->subMonths(2),
        'rent_due_day' => 5,
    ]);

    $tenantB = Tenant::factory()->create(['name' => 'Siti Rahma']);
    Lease::factory()->create([
        'primary_tenant_id' => $tenantB->id,
        'start_date' => now()->subMonths(2),
        'rent_due_day' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.rent', ['search' => 'budi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.tenant_name', 'Budi Santoso')
        );

    Carbon::setTestNow();
});

test('rent dashboard requires authentication', function () {
    $this->get(route('dashboard.rent'))->assertRedirect('login');
});

test('rent dashboard requires dashboard.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertForbidden();
});

test('rent dashboard shows days overdue correctly', function () {
    Carbon::setTestNow(now()->setDay(15));

    $user = User::factory()->owner()->create();

    createLeaseWithPayment(['rent_due_day' => 10]);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.rent_status', 'overdue')
            ->where('entries.data.0.days_overdue', 5)
        );

    Carbon::setTestNow();
});

test('rent dashboard scopes to user properties for non-owner', function () {
    Carbon::setTestNow(now()->setDay(10));

    $user = User::factory()->admin()->create();

    $propertyA = Property::factory()->create();
    $tenantA = Tenant::factory()->create();
    Lease::factory()->create([
        'unit_id' => Unit::factory()->create(['property_id' => $propertyA->id])->id,
        'primary_tenant_id' => $tenantA->id,
        'start_date' => now()->subMonths(2),
        'rent_due_day' => 5,
    ]);

    $propertyB = Property::factory()->create();
    $tenantB = Tenant::factory()->create();
    Lease::factory()->create([
        'unit_id' => Unit::factory()->create(['property_id' => $propertyB->id])->id,
        'primary_tenant_id' => $tenantB->id,
        'start_date' => now()->subMonths(2),
        'rent_due_day' => 3,
    ]);

    $propertyA->users()->attach($user->id);

    $this->actingAs($user)
        ->get(route('dashboard.rent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.tenant_name', $tenantA->name)
        );

    Carbon::setTestNow();
});
