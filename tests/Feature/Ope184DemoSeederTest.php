<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\City;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LoadTestDatasetSeeder;
use Database\Seeders\LoadTestSeeder;

it('seeds a complete currency-aware demo graph and can be rerun', function () {
    $this->travelTo('2026-09-15 10:00:00');

    $this->seed(DatabaseSeeder::class);

    $demoProperties = Property::query()->where('slug', 'like', 'ope-184-demo-%');
    $demoLeases = Lease::query()->where('reference', 'like', 'ope-184-demo-lease-%');
    $demoHistory = Lease::query()->where('reference', 'like', 'ope-184-demo-history-%');
    $demoInvoices = Invoice::query()->whereHas('lease', fn ($query) => $query->where('reference', 'like', 'ope-184-demo-%'));

    expect($demoProperties->count())->toBe(10)
        ->and(Unit::query()->whereHas('property', fn ($query) => $query->where('slug', 'like', 'ope-184-demo-%'))->count())->toBe(120)
        ->and(UnitRate::query()
            ->whereHas('unit.property', fn ($query) => $query->where('slug', 'like', 'ope-184-demo-%'))
            ->select(['unit_id', 'billing_interval', 'billing_unit', 'currency'])
            ->groupBy(['unit_id', 'billing_interval', 'billing_unit', 'currency'])
            ->havingRaw('COUNT(*) > 1')
            ->get()->count())->toBe(0)
        ->and($demoLeases->count())->toBe(10)
        ->and($demoLeases->where('status', LeaseStatus::Active)->count())->toBe(10)
        ->and($demoHistory->count())->toBe(7)
        ->and($demoInvoices->count())->toBeGreaterThan(0)
        ->and(MaintenanceTicket::query()->where('reference', 'like', 'ope-184-demo-ticket-%')->count())->toBe(3)
        ->and(User::query()->whereIn('email', ['demo.staff@openkos.com', 'demo.tenant@openkos.com'])->count())->toBe(2);

    $usdLease = Lease::query()->where('reference', 'ope-184-demo-lease-004')->firstOrFail();
    $usdInvoice = $usdLease->invoices()->where('currency', 'USD')->where('period_start', '<=', today())->latest('period_start')->firstOrFail();
    $usdPayment = $usdInvoice->payments()->where('status', PaymentStatus::Confirmed)->firstOrFail();
    $idLease = Lease::query()->where('reference', 'ope-184-demo-lease-006')->firstOrFail();
    $idInvoice = $idLease->invoices()->where('currency', 'IDR')->where('period_start', '<=', today())->latest('period_start')->firstOrFail();
    $idPayment = $idInvoice->payments()->where('status', PaymentStatus::Confirmed)->firstOrFail();

    expect($usdLease->currency)->toBe('USD')
        ->and($usdInvoice->currency)->toBe('USD')
        ->and($usdPayment->currency)->toBe('USD')
        ->and($usdPayment->allocations()->where('invoice_id', $usdInvoice->id)->exists())->toBeTrue()
        ->and($idLease->currency)->toBe('IDR')
        ->and($idInvoice->currency)->toBe('IDR')
        ->and($idPayment->currency)->toBe('IDR')
        ->and($idPayment->allocations()->where('invoice_id', $idInvoice->id)->exists())->toBeTrue()
        ->and(UnitRate::query()->where('currency', 'USD')->where('billing_interval', 1)->where('billing_unit', 'month')->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', InvoiceStatus::Partial)->whereHas('lease', fn ($query) => $query->where('reference', 'like', 'ope-184-demo-%'))->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', InvoiceStatus::Paid)->whereHas('lease', fn ($query) => $query->where('reference', 'like', 'ope-184-demo-%'))->exists())->toBeTrue()
        ->and(Payment::query()->where('status', PaymentStatus::Pending)->where('reference_number', 'like', 'ope-184-demo-%')->exists())->toBeTrue()
        ->and(Unit::query()->where('status', UnitStatus::Maintenance)->whereHas('property', fn ($query) => $query->where('slug', 'like', 'ope-184-demo-%'))->exists())->toBeTrue()
        ->and(MaintenanceTicket::query()->where('status', MaintenanceStatus::Reported)->where('reference', 'ope-184-demo-ticket-001')->exists())->toBeTrue()
        ->and(Tenant::query()->whereHas('user', fn ($query) => $query->where('email', 'demo.tenant@openkos.com'))->exists())->toBeTrue();

    foreach ($demoHistory->with('unitRate')->get() as $historyLease) {
        expect($historyLease->currency)->toBe($historyLease->unitRate->currency)
            ->and((float) $historyLease->deposit_refund_amount)->toBeLessThanOrEqual((float) $historyLease->deposit_amount)
            ->and($historyLease->termination_date->toDateString())->toBe($historyLease->end_date->toDateString())
            ->and($historyLease->status)->toBe(LeaseStatus::Terminated);
    }

    $counts = [
        'properties' => $demoProperties->count(),
        'units' => Unit::query()->whereHas('property', fn ($query) => $query->where('slug', 'like', 'ope-184-demo-%'))->count(),
        'leases' => $demoLeases->count(),
        'history' => $demoHistory->count(),
        'invoices' => $demoInvoices->count(),
        'payments' => Payment::query()->where('reference_number', 'like', 'ope-184-demo-%')->count(),
        'tickets' => MaintenanceTicket::query()->where('reference', 'like', 'ope-184-demo-ticket-%')->count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect([
        'properties' => Property::query()->where('slug', 'like', 'ope-184-demo-%')->count(),
        'units' => Unit::query()->whereHas('property', fn ($query) => $query->where('slug', 'like', 'ope-184-demo-%'))->count(),
        'leases' => Lease::query()->where('reference', 'like', 'ope-184-demo-lease-%')->count(),
        'history' => Lease::query()->where('reference', 'like', 'ope-184-demo-history-%')->count(),
        'invoices' => Invoice::query()->whereHas('lease', fn ($query) => $query->where('reference', 'like', 'ope-184-demo-%'))->count(),
        'payments' => Payment::query()->where('reference_number', 'like', 'ope-184-demo-%')->count(),
        'tickets' => MaintenanceTicket::query()->where('reference', 'like', 'ope-184-demo-ticket-%')->count(),
    ])->toBe($counts);
});

it('preserves the OPE-177 dataset when the normal demo seed runs', function () {
    $loadTestConfig = [
        'enabled' => true,
        'users' => [
            'owner' => ['name' => 'Load Test Owner', 'email' => 'owner.load-test@example.com', 'password' => 'owner-secret'],
            'admin' => ['name' => 'Load Test Manager', 'email' => 'manager.load-test@example.com', 'password' => 'manager-secret'],
            'staff' => ['name' => 'Load Test Staff', 'email' => 'staff.load-test@example.com', 'password' => 'staff-secret'],
            'tenant' => ['name' => 'Load Test Tenant', 'email' => 'tenant.load-test@example.com', 'password' => 'tenant-secret'],
        ],
    ];
    config([
        'load-test.fixtures' => $loadTestConfig,
        'load-test.dataset.enabled' => true,
    ]);

    $this->seed(LoadTestSeeder::class);
    $region = Region::factory()->create(['country_code' => 'ID', 'name' => 'OPE-177 Test Region']);
    $city = City::factory()->for($region)->create(['name' => 'OPE-177 Test City']);
    $this->seed(LoadTestDatasetSeeder::class);

    $property = Property::query()->where('slug', 'ope-177-load-test-property-01')->firstOrFail();
    $unit = $property->units()->where('name', 'OPE-177 Unit 01')->firstOrFail();
    $before = [
        'property' => [$property->name, $property->region_id, $property->city_id],
        'unit' => [$unit->name, $unit->status->value],
    ];

    $this->seed(DatabaseSeeder::class);

    $property->refresh();
    $unit->refresh();

    expect([$property->name, $property->region_id, $property->city_id])->toBe($before['property'])
        ->and([$unit->name, $unit->status->value])->toBe($before['unit'])
        ->and(Region::query()->findOrFail($region->id)->name)->toBe('OPE-177 Test Region')
        ->and(City::query()->findOrFail($city->id)->name)->toBe('OPE-177 Test City')
        ->and(Property::query()->where('slug', 'ope-177-load-test-property-01')->count())->toBe(1)
        ->and(Unit::query()->where('property_id', $property->id)->where('name', 'OPE-177 Unit 01')->count())->toBe(1);
});

it('keeps payment allocation factories aligned with their payment', function () {
    $allocation = PaymentAllocation::factory()->create();

    expect($allocation->invoice_id)->toBe($allocation->payment->invoice_id)
        ->and((string) $allocation->amount)->toBe((string) $allocation->payment->amount);
});
