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
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreignId('utility_reading_id')
                ->nullable()
                ->after('amount')
                ->unique()
                ->constrained('utility_readings')
                ->restrictOnDelete();
            $table->json('metadata')->nullable()->after('utility_reading_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['utility_reading_id']);
            $table->dropUnique('invoice_line_items_utility_reading_id_unique');
            $table->dropColumn(['utility_reading_id', 'metadata']);
        });
    }
};
