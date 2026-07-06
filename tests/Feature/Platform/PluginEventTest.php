<?php

use App\Events\Payment\PaymentRecorded;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class EventProbePlugin extends Plugin
{
    public static bool $fired = false;

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/event', name: 'Event', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void {}

    public function listens(): array
    {
        return [
            PaymentRecorded::class => fn (PaymentRecorded $event) => static::$fired = true,
        ];
    }
}

it('wires a plugins event listeners at boot', function () {
    EventProbePlugin::$fired = false;
    config(['platform.plugins' => [EventProbePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();

    PaymentRecorded::dispatch(Payment::factory()->make());

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

    $this->actingAs($user)->post(route('leases.payments.store', $lease), [
        'amount' => 1_500_000,
        'payment_method' => 'cash',
        'paid_at' => now()->format('Y-m-d'),
        'period_month' => now()->month,
        'period_year' => now()->year,
    ]);

    Event::assertDispatched(PaymentRecorded::class);
});
