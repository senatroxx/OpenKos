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
        Schema::table('amenities', function (Blueprint $table): void {
            $table->string('scope')->default('both')->after('name');
            $table->index(['scope', 'is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropIndex('amenities_scope_is_active_name_index');
            $table->dropColumn('scope');
        });
    }
};
