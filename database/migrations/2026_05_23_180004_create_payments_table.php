<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('payment_method')->default('cash');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('status');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
