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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 3);
            $table->char('currency', 3);
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('billing_interval');
            $table->string('billing_unit', 10);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('next_due_on')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_due_on']);
            $table->index(['property_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
