<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 255)->default('OpenKOS');
            $table->string('country_code', 2)->default('ID');
            $table->string('locale', 10)->default('id');
            $table->string('currency', 3)->default('IDR');
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->timestamps();
            $table->string('lease_id_prefix', 10)->default('LSX');
            $table->boolean('reminder_enabled')->default(true);
            $table->smallint('reminder_days_before')->default(3);
            $table->json('reminder_overdue_intervals')->default('[1,3,7]');
            $table->text('reminder_message_template')->nullable();
            $table->string('mail_driver', 255)->default('smtp');
            $table->string('mail_host', 255)->nullable();
            $table->integer('mail_port')->nullable();
            $table->string('mail_username', 255)->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption', 255)->nullable();
            $table->string('mail_from_address', 255)->nullable();
            $table->string('mail_from_name', 255)->nullable();
            $table->text('reminder_channels')->nullable();
            $table->string('whatsapp_driver', 255)->nullable();
            $table->text('whatsapp_config')->nullable();
        });
    }
};
