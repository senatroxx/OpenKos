<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_types', function (Blueprint $table) {
            $table->string('default_rental_mode')->default('unit')->after('label');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('rental_mode')->default('unit')->after('type');
        });

        DB::table('property_types')->where('slug', 'villa')->update([
            'default_rental_mode' => 'whole_property',
        ]);

        DB::table('properties')->update(['rental_mode' => 'unit']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('rental_mode');
        });

        Schema::table('property_types', function (Blueprint $table) {
            $table->dropColumn('default_rental_mode');
        });
    }
};
