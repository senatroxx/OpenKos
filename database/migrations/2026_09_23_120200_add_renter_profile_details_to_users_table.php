<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('id_card_number', 50)->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('id_card_number');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'id_card_number',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
