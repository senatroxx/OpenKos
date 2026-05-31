<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->renameColumn('monthly_rent', 'rent_amount');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_interval')->default(1)->after('rent_amount');
            $table->string('billing_unit')->default('month')->after('billing_interval');
            $table->foreignId('room_rate_id')->nullable()->after('billing_unit')->constrained()->nullOnDelete();
            $table->boolean('is_custom_price')->default(false)->after('room_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['billing_interval', 'billing_unit', 'room_rate_id', 'is_custom_price']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->renameColumn('rent_amount', 'monthly_rent');
        });
    }
};
