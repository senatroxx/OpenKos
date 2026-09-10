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
        Schema::table('utility_meters', function (Blueprint $table) {
            $table->string('utility_name')->nullable()->after('utility_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_meters', function (Blueprint $table) {
            $table->dropColumn('utility_name');
        });
    }
};
