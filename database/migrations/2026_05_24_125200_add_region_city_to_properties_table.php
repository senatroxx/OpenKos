<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['city', 'province']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['city_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['region_id', 'city_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('city')->nullable();
            $table->string('province')->nullable();
        });
    }
};
