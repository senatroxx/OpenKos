<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->dropUnique('unit_rates_unit_id_billing_interval_billing_unit_unique');
            $table->unique(
                ['unit_id', 'billing_interval', 'billing_unit', 'currency'],
                'unit_rates_unit_interval_unit_currency_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasVariants = DB::table('unit_rates')
            ->select(['unit_id', 'billing_interval', 'billing_unit'])
            ->groupBy(['unit_id', 'billing_interval', 'billing_unit'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasVariants) {
            throw new RuntimeException(
                'Cannot restore unit-rate uniqueness while currency variants exist.',
            );
        }

        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->dropUnique('unit_rates_unit_interval_unit_currency_unique');
            $table->unique(
                ['unit_id', 'billing_interval', 'billing_unit'],
                'unit_rates_unit_id_billing_interval_billing_unit_unique',
            );
        });
    }
};
