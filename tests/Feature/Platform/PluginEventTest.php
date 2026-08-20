<?php

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use OpenKOS\Core\Events\PaymentRecorded;
use OpenKOS\Platform\PlatformServiceProvider;
use Tests\Support\Fixtures\EventProbePlugin;

it('wires a plugins event listeners at boot', function () {
    EventProbePlugin::$fired = false;
    config(['platform.plugins' => [EventProbePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();

    event(new PaymentRecorded(paymentId: 1));

    expect(EventProbePlugin::$fired)->toBeTrue();
});

it('dispatches PaymentRecorded when a payment is recorded', function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
    Event::fake([PaymentRecorded::class]);

    $user = User::factory()->owner()->create();
    $unit = Unit::factory()->for(Property::factory())->create();
    $lease = Lease::factory()->create([
        'unit_id' => $unit->id,
        'primary_tenant_id' => Tenant::factory()->create()->id,
    ]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'total' => 1_500_000,
    ]);

    $this->actingAs($user)->post(route('leases.payments.store', $lease), [
        'invoice_id' => $invoice->id,
        'amount' => 1_500_000,
        'payment_method' => 'cash',
        'paid_at' => now()->format('Y-m-d'),
    ]);

    Event::assertDispatched(PaymentRecorded::class);
});
