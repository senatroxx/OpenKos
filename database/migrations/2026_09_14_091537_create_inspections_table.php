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
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_template_id')->constrained()->restrictOnDelete();
            $table->string('template_name');
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('inspection_type', 32);
            $table->date('inspection_date');
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->text('damage_observations')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'inspection_date']);
            $table->index(['unit_id', 'inspection_date']);
            $table->index(['lease_id', 'inspection_date']);
            $table->index(['status', 'inspection_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
