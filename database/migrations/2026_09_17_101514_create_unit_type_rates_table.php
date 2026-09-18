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
        Schema::create('unit_type_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_type_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('billing_interval');
            $table->string('billing_unit');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(
                ['unit_type_id', 'billing_interval', 'billing_unit', 'currency'],
                'unit_type_rates_type_interval_unit_currency_unique',
            );
            $table->index(
                ['unit_type_id', 'is_active', 'billing_interval'],
                'idx_unit_type_rates_type_active_interval',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_type_rates');
    }
};
