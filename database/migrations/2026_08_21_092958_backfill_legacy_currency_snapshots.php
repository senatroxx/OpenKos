<?php

use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configuredCurrency = DB::table('settings')
            ->where('key', 'currency')
            ->value('value');
        $currency = app(MoneyConverter::class)->normalizeCurrency($configuredCurrency);

        foreach (['unit_rates', 'leases', 'invoices', 'payments'] as $table) {
            DB::table($table)
                ->whereNull('currency')
                ->update(['currency' => $currency]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible migration: legacy currency snapshots cannot be safely restored.');
    }
};
