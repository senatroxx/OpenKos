<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('rental_billing_unit', 16)->nullable()->after('intended_move_in_date');
            $table->unsignedSmallInteger('rental_billing_interval')->nullable()->after('rental_billing_unit');
            $table->string('rental_currency', 3)->nullable()->after('rental_billing_interval');
            $table->decimal('rental_amount', 18, 3)->nullable()->after('rental_currency');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'rental_billing_unit',
                'rental_billing_interval',
                'rental_currency',
                'rental_amount',
            ]);
        });
    }
};
