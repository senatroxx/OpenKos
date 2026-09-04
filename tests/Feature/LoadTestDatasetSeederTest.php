<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\City;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\LoadTestDatasetSeeder;
use Database\Seeders\LoadTestSeeder;

function datasetFixtureConfig(bool $fixturesEnabled = true): array
{
    return [
        'enabled' => $fixturesEnabled,
        'users' => [
            'owner' => ['name' => 'Load Test Owner', 'email' => 'owner.load-test@example.com', 'password' => 'owner-secret'],
            'admin' => ['name' => 'Load Test Manager', 'email' => 'manager.load-test@example.com', 'password' => 'manager-secret'],
            'staff' => ['name' => 'Load Test Staff', 'email' => 'staff.load-test@example.com', 'password' => 'staff-secret'],
            'tenant' => ['name' => 'Load Test Tenant', 'email' => 'tenant.load-test@example.com', 'password' => 'tenant-secret'],
        ],
    ];
}

function configureDataset(bool $fixturesEnabled = true, bool $datasetEnabled = true): void
{
    config([
        'load-test.fixtures' => datasetFixtureConfig($fixturesEnabled),
        'load-test.dataset.enabled' => $datasetEnabled,
    ]);
}

function seedDatasetBaseData(): void
{
    $region = Region::factory()->create([
        'country_code' => 'ID',
        'name' => 'Load Test Region',
    ]);

    City::factory()->for($region)->create(['name' => 'Load Test City']);
}

function datasetCounts(): array
{
    return [
        'properties' => Property::where('slug', 'like', 'ope-177-load-test-property-%')->count(),
        'units' => Unit::where('name', 'like', 'OPE-177 Unit %')->count(),
        'tenants' => Tenant::where(function ($query): void {
            $query->where('id_card_number', 'load-test-tenant')
                ->orWhere('id_card_number', 'like', 'ope-177-load-test-tenant-%');
        })->count(),
        'active_leases' => Lease::where('reference', 'like', 'ope-177-load-test-lease-%')->count(),
        'historical_leases' => Lease::where('reference', 'like', 'ope-177-load-test-history-%')->count(),
        'invoices' => Invoice::where('reference', 'like', 'ope-177-load-test-invoice-%')->count(),
        'payments' => Payment::where('reference_number', 'like', 'ope-177-load-test-payment-%')->count(),
        'maintenance_tickets' => MaintenanceTicket::where('reference', 'like', 'ope-177-load-test-ticket-%')->count(),
    ];
}

test('requires both load-test gates before seeding the dataset', function () {
    configureDataset(false, true);

    expect(fn () => $this->seed(LoadTestDatasetSeeder::class))
        ->toThrow(RuntimeException::class, 'LOAD_TEST_FIXTURES_ENABLED=true');

    configureDataset(true, false);

    expect(fn () => $this->seed(LoadTestDatasetSeeder::class))
        ->toThrow(RuntimeException::class, 'LOAD_TEST_DATASET_ENABLED=true');

    expect(datasetCounts())->each->toBe(0);
});

test('requires the OPE-176 users before writing dataset records', function () {
    configureDataset();

    expect(fn () => $this->seed(LoadTestDatasetSeeder::class))
        ->toThrow(RuntimeException::class, 'Run LoadTestSeeder first');

    expect(User::query()->count())->toBe(0)
        ->and(datasetCounts())->each->toBe(0);
});

test('seeds the documented dataset and connects the tenant portal', function () {
    configureDataset();
    $this->seed(LoadTestSeeder::class);
    seedDatasetBaseData();

    $this->seed(LoadTestDatasetSeeder::class);

    expect(datasetCounts())->toBe([
        'properties' => 8,
        'units' => 96,
        'tenants' => 48,
        'active_leases' => 48,
        'historical_leases' => 12,
        'invoices' => 144,
        'payments' => 84,
        'maintenance_tickets' => 24,
    ]);

    expect(Invoice::where('reference', 'like', 'ope-177-load-test-invoice-%')
        ->where('status', InvoiceStatus::Paid)
        ->count())->toBe(60)
        ->and(Invoice::where('reference', 'like', 'ope-177-load-test-invoice-%')
            ->where('status', InvoiceStatus::Partial)
            ->count())->toBe(12)
        ->and(Invoice::where('reference', 'like', 'ope-177-load-test-invoice-%')
            ->where('status', InvoiceStatus::Pending)
            ->count())->toBe(72)
        ->and(Payment::where('reference_number', 'like', 'ope-177-load-test-payment-%')
            ->where('status', PaymentStatus::Pending)
            ->count())->toBe(12);

    $owner = User::whereEmail('owner.load-test@example.com')->firstOrFail();
    $admin = User::whereEmail('manager.load-test@example.com')->firstOrFail();
    $staff = User::whereEmail('staff.load-test@example.com')->firstOrFail();
    $tenantUser = User::whereEmail('tenant.load-test@example.com')->firstOrFail();
    $tenant = $tenantUser->tenant()->firstOrFail();
    $activeLease = $tenant->leases()->where('status', LeaseStatus::Active)->firstOrFail();

    expect($owner->isOwner())->toBeTrue()
        ->and($admin->properties()->count())->toBe(8)
        ->and($staff->properties()->count())->toBe(4)
        ->and($activeLease->reference)->toBe('ope-177-load-test-lease-001')
        ->and($activeLease->unit->status)->toBe(UnitStatus::Occupied)
        ->and($activeLease->invoices()->count())->toBe(3)
        ->and($activeLease->invoices()->where('status', InvoiceStatus::Paid)->exists())->toBeTrue();

    $this->actingAs($tenantUser)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/dashboard')
            ->where('tenant.name', 'Load Test Tenant')
            ->where('lease.reference', 'ope-177-load-test-lease-001'));
});

test('keeps fixture counts and relationships stable when seeded repeatedly', function () {
    configureDataset();
    $this->seed(LoadTestSeeder::class);
    seedDatasetBaseData();

    $this->seed(LoadTestDatasetSeeder::class);
    $counts = datasetCounts();
    $leases = Lease::where('reference', 'like', 'ope-177-load-test-lease-%')
        ->orderBy('reference')
        ->get(['reference', 'primary_tenant_id', 'unit_id'])
        ->mapWithKeys(fn (Lease $lease): array => [
            $lease->reference => [$lease->primary_tenant_id, $lease->unit_id, $lease->tenants()->pluck('tenant_id')->all()],
        ])->all();

    $this->seed(LoadTestDatasetSeeder::class);

    expect(datasetCounts())->toBe($counts)
        ->and(Lease::where('reference', 'like', 'ope-177-load-test-lease-%')->count())->toBe(48)
        ->and(Lease::where('reference', 'like', 'ope-177-load-test-lease-%')
            ->where('status', LeaseStatus::Active)
            ->whereHas('tenants', fn ($query) => $query->where('is_primary', true))
            ->count())->toBe(48)
        ->and(Lease::where('reference', 'like', 'ope-177-load-test-lease-%')
            ->orderBy('reference')
            ->get(['reference', 'primary_tenant_id', 'unit_id'])
            ->mapWithKeys(fn (Lease $lease): array => [
                $lease->reference => [$lease->primary_tenant_id, $lease->unit_id, $lease->tenants()->pluck('tenant_id')->all()],
            ])->all())->toBe($leases);
});

test('leaves unrelated application data untouched', function () {
    configureDataset();
    $this->seed(LoadTestSeeder::class);
    seedDatasetBaseData();

    $property = Property::factory()->create(['name' => 'Existing application property']);
    $unit = Unit::factory()->for($property)->create(['name' => 'Existing application unit']);
    $tenant = Tenant::factory()->create(['name' => 'Existing application tenant']);

    $this->seed(LoadTestDatasetSeeder::class);

    expect(Property::query()->findOrFail($property->id)->name)->toBe('Existing application property')
        ->and(Unit::query()->findOrFail($unit->id)->name)->toBe('Existing application unit')
        ->and(Tenant::query()->findOrFail($tenant->id)->name)->toBe('Existing application tenant');
});

test('rejects dataset seeding in production', function () {
    configureDataset();
    $this->app->instance('env', 'production');

    expect(fn () => app(LoadTestDatasetSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'cannot be seeded in production');
});
