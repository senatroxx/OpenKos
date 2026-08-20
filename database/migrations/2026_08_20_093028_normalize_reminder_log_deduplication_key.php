<?php

use App\Models\ReminderLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureNormalizedKeysAreUnique();

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropUnique('reminder_logs_unique');
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->smallInteger('overdue_days')
                ->nullable()
                ->default(null)
                ->change();
        });

        DB::table('reminder_logs')
            ->whereNull('overdue_days')
            ->update(['overdue_days' => ReminderLog::NON_OVERDUE_DAYS]);

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->smallInteger('overdue_days')
                ->default(ReminderLog::NON_OVERDUE_DAYS)
                ->nullable(false)
                ->change();
            $table->unique(
                ['lease_id', 'period_start', 'reminder_type', 'overdue_days'],
                'reminder_logs_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropUnique('reminder_logs_unique');
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->smallInteger('overdue_days')
                ->nullable()
                ->default(null)
                ->change();
        });

        DB::table('reminder_logs')
            ->where('overdue_days', ReminderLog::NON_OVERDUE_DAYS)
            ->update(['overdue_days' => null]);

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('overdue_days')
                ->nullable()
                ->default(null)
                ->change();
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->unique(
                ['lease_id', 'period_start', 'reminder_type', 'overdue_days'],
                'reminder_logs_unique',
            );
        });
    }

    private function ensureNormalizedKeysAreUnique(): void
    {
        $hasDuplicates = DB::table('reminder_logs')
            ->select('lease_id', 'period_start', 'reminder_type')
            ->selectRaw(
                'COALESCE(overdue_days, ?) AS normalized_overdue_days',
                [ReminderLog::NON_OVERDUE_DAYS],
            )
            ->groupBy('lease_id', 'period_start', 'reminder_type')
            ->groupByRaw(
                'COALESCE(overdue_days, ?)',
                [ReminderLog::NON_OVERDUE_DAYS],
            )
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Cannot normalize reminder_logs.overdue_days because duplicate reminder keys exist.',
            );
        }
    }
};
