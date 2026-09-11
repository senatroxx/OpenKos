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
        Schema::create('deposit_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_settlement_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 20, 3);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_deductions');
    }
};
