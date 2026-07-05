<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * base_price was the original single-price field, superseded by room_rates
 * (2026_05_31_034600) which backfilled it into a monthly rate. It has since
 * been unused (not fillable, no reads, no UI). Drop it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('base_price', 12, 2)->nullable();
        });
    }
};
