<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->foreignId('unit_type_rate_id')
                ->nullable()
                ->after('unit_rate_id')
                ->constrained('unit_type_rates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_type_rate_id');
        });
    }
};
