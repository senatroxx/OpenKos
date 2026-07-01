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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('whatsapp_driver')->nullable()->after('mail_from_name');
            $table->text('whatsapp_config')->nullable()->after('whatsapp_driver');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_driver', 'whatsapp_config']);
        });
    }
};
