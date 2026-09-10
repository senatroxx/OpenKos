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
        Schema::create('utility_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_meter_id')->constrained('utility_meters')->restrictOnDelete();
            $table->string('reading_kind')->default('reading');
            $table->date('reading_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('previous_reading_id')->nullable()->constrained('utility_readings')->restrictOnDelete();
            $table->decimal('previous_reading', 20, 3);
            $table->decimal('current_reading', 20, 3);
            $table->decimal('consumption', 20, 3);
            $table->decimal('adjustment_consumption', 20, 3)->nullable();
            $table->decimal('rate', 20, 3);
            $table->char('currency', 3);
            $table->string('reference')->nullable();
            $table->foreignId('corrects_reading_id')->nullable()->constrained('utility_readings')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['utility_meter_id', 'period_start', 'period_end', 'reading_kind'],
                'utility_readings_meter_period_kind_unique',
            );
            $table->index(
                ['utility_meter_id', 'period_start', 'period_end'],
                'idx_utility_readings_meter_period',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_readings');
    }
};
