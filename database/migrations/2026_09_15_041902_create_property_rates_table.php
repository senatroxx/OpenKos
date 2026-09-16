<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('billing_interval');
            $table->string('billing_unit');
            $table->decimal('amount', 20, 3);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(
                ['property_id', 'billing_interval', 'billing_unit', 'currency'],
                'property_rates_property_interval_unit_currency_unique',
            );
            $table->index(
                ['property_id', 'is_active', 'billing_interval'],
                'property_rates_property_active_interval_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_rates');
    }
};
