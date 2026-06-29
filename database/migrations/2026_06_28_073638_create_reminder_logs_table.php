<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('reminder_type');
            $table->unsignedTinyInteger('overdue_days')->nullable();
            $table->string('notification_class');
            $table->string('channel');
            $table->date('scheduled_for');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['lease_id', 'period_start', 'reminder_type', 'overdue_days'], 'reminder_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
