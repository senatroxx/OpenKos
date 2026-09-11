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
        Schema::table('properties', function (Blueprint $table) {
            $table->string('public_slug')->nullable()->unique();
            $table->boolean('is_published')->default(false);
            $table->index(['is_active', 'is_published']);
        });

        Schema::table('unit_types', function (Blueprint $table) {
            $table->string('public_slug')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unique(['property_id', 'public_slug']);
            $table->index(['property_id', 'is_active', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'is_active', 'is_published']);
            $table->dropUnique(['property_id', 'public_slug']);
            $table->dropColumn(['public_slug', 'is_published']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'is_published']);
            $table->dropUnique(['public_slug']);
            $table->dropColumn(['public_slug', 'is_published']);
        });
    }
};
