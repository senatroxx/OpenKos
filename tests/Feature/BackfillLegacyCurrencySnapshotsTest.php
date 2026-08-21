<?php

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

it('backfills null currency snapshots from the current setting', function () {
    Setting::set('currency', 'IDR');

    $legacyUnit = Unit::factory()->create();
    $legacyRate = $legacyUnit->rates()->firstOrFail();
    $legacyLease = Lease::factory()->create([
        'unit_id' => $legacyUnit->id,
        'unit_rate_id' => $legacyRate->id,
        'rent_amount' => 1_500_000,
        'currency' => 'USD',
    ]);
    $legacyInvoice = Invoice::factory()->create([
        'lease_id' => $legacyLease->id,
        'currency' => 'USD',
    ]);
    $legacyPayment = Payment::factory()->create([
        'invoice_id' => $legacyInvoice->id,
        'currency' => 'USD',
    ]);

    $explicitUnit = Unit::factory()->create();
    $explicitRate = $explicitUnit->rates()->firstOrFail();
    DB::table('unit_rates')->where('id', $explicitRate->id)->update(['currency' => 'USD']);
    $explicitLease = Lease::factory()->create([
        'unit_id' => $explicitUnit->id,
        'unit_rate_id' => $explicitRate->id,
        'rent_amount' => 1_500_000,
        'currency' => 'USD',
    ]);
    $explicitInvoice = Invoice::factory()->create([
        'lease_id' => $explicitLease->id,
        'currency' => 'USD',
    ]);
    $explicitPayment = Payment::factory()->create([
        'invoice_id' => $explicitInvoice->id,
        'currency' => 'USD',
    ]);

    DB::table('unit_rates')->where('id', $legacyRate->id)->update(['currency' => null]);
    DB::table('leases')->where('id', $legacyLease->id)->update(['currency' => null]);
    DB::table('invoices')->where('id', $legacyInvoice->id)->update(['currency' => null]);
    DB::table('payments')->where('id', $legacyPayment->id)->update(['currency' => null]);

    $migration = require database_path('migrations/2026_08_21_092958_backfill_legacy_currency_snapshots.php');
    $migration->up();

    expect(DB::table('unit_rates')->where('id', $legacyRate->id)->value('currency'))->toBe('IDR')
        ->and(DB::table('leases')->where('id', $legacyLease->id)->value('currency'))->toBe('IDR')
        ->and(DB::table('invoices')->where('id', $legacyInvoice->id)->value('currency'))->toBe('IDR')
        ->and(DB::table('payments')->where('id', $legacyPayment->id)->value('currency'))->toBe('IDR')
        ->and(DB::table('unit_rates')->where('id', $explicitRate->id)->value('currency'))->toBe('USD')
        ->and(DB::table('leases')->where('id', $explicitLease->id)->value('currency'))->toBe('USD')
        ->and(DB::table('invoices')->where('id', $explicitInvoice->id)->value('currency'))->toBe('USD')
        ->and(DB::table('payments')->where('id', $explicitPayment->id)->value('currency'))->toBe('USD');
});
