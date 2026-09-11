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
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_type_id')->nullable()->after('property_id');
            $table->index('unit_type_id');
            $table->foreign(['unit_type_id', 'property_id'], 'units_unit_type_property_fk')
                ->references(['id', 'property_id'])
                ->on('unit_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign('units_unit_type_property_fk');
            $table->dropIndex(['unit_type_id']);
            $table->dropColumn('unit_type_id');
        });
    }
};
