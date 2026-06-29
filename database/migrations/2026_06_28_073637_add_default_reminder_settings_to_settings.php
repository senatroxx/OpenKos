<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('reminder_enabled')->default(true);
            $table->unsignedTinyInteger('reminder_days_before')->default(3);
            $table->json('reminder_overdue_intervals')->default('[1,3,7]');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['reminder_enabled', 'reminder_days_before', 'reminder_overdue_intervals']);
        });
    }
};
