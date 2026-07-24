<?php

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

test('lease index shows pending payment verification count in stats', function () {
    $user = User::factory()->owner()->create();

    $lease = Lease::factory()->create();
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    Payment::factory()->pending()->create(['invoice_id' => $invoice->id]);
    Payment::factory()->pending()->create(['invoice_id' => $invoice->id]);

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leases/index')
            ->where('stats.pending_payment_verification', 2)
            ->where('leases.data.0.pending_payment_review_count', 2)
        );
});

test('lease index scopes pending payment verification count to accessible properties', function () {
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
        ->get(route('leases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leases/index')
            ->where('stats.pending_payment_verification', 1)
        );
});
