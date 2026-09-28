<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->date('move_in_date');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['unit_id', 'status', 'expires_at']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_confirmed_hold_check CHECK (status <> 'confirmed' OR (confirmed_at IS NOT NULL AND expires_at IS NOT NULL))");
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER reservations_confirmed_hold_insert BEFORE INSERT ON reservations BEGIN SELECT RAISE(ABORT, 'Confirmed reservations require hold timestamps') WHERE NEW.status = 'confirmed' AND (NEW.confirmed_at IS NULL OR NEW.expires_at IS NULL); END");
            DB::statement("CREATE TRIGGER reservations_confirmed_hold_update BEFORE UPDATE OF status, confirmed_at, expires_at ON reservations BEGIN SELECT RAISE(ABORT, 'Confirmed reservations require hold timestamps') WHERE NEW.status = 'confirmed' AND (NEW.confirmed_at IS NULL OR NEW.expires_at IS NULL); END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS reservations_confirmed_hold_insert');
            DB::statement('DROP TRIGGER IF EXISTS reservations_confirmed_hold_update');
        }

        Schema::dropIfExists('reservations');
    }
};
