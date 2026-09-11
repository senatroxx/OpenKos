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
        Schema::create('deposit_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('original_amount', 20, 3);
            $table->char('currency', 3);
            $table->string('status')->default('draft');
            $table->date('settlement_date');
            $table->decimal('refund_amount', 20, 3)->default(0);
            $table->string('refund_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_settlements');
    }
};
