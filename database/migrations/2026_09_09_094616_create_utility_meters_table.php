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
        Schema::create('utility_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('utility_type');
            $table->string('identifier');
            $table->string('measurement_unit');
            $table->decimal('rate', 20, 3);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['unit_id', 'identifier']);
            $table->index(['unit_id', 'is_active'], 'idx_utility_meters_unit_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_meters');
    }
};
