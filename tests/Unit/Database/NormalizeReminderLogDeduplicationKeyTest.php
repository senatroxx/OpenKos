<?php

use App\Models\ReminderLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->originalDatabaseConnection = DB::getDefaultConnection();

    config([
        'database.connections.ope_migration' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('ope_migration');
    DB::setDefaultConnection('ope_migration');

    Schema::create('reminder_logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('lease_id');
        $table->date('period_start');
        $table->string('reminder_type');
        $table->unsignedTinyInteger('overdue_days')->nullable();
        $table->unique(
            ['lease_id', 'period_start', 'reminder_type', 'overdue_days'],
            'reminder_logs_unique',
        );
    });
});

afterEach(function (): void {
    DB::disconnect('ope_migration');
    DB::purge('ope_migration');
    DB::setDefaultConnection($this->originalDatabaseConnection);
});

it('round-trips null and zero overdue days through the migration', function (): void {
    DB::table('reminder_logs')->insert([
        [
            'lease_id' => 1,
            'period_start' => '2026-07-01',
            'reminder_type' => 'upcoming',
            'overdue_days' => null,
        ],
        [
            'lease_id' => 1,
            'period_start' => '2026-07-01',
            'reminder_type' => 'overdue',
            'overdue_days' => 0,
        ],
    ]);

    $migration = require database_path(
        'migrations/2026_08_20_093028_normalize_reminder_log_deduplication_key.php',
    );

    $migration->up();

    expect(normalizedOverdueDays())->toBe([ReminderLog::NON_OVERDUE_DAYS, 0]);

    $migration->down();

    expect(normalizedOverdueDays())->toBe([null, 0]);
});

function normalizedOverdueDays(): array
{
    return DB::table('reminder_logs')
        ->orderBy('id')
        ->pluck('overdue_days')
        ->map(fn ($days) => $days === null ? null : (int) $days)
        ->all();
}
