<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->foreignId('property_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->string('location')->nullable()->after('room_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE maintenance_tickets ALTER COLUMN room_id DROP NOT NULL');
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->dropForeign(['room_id']);
            });

            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('room_id_new')->nullable()->after('location');
            });

            DB::statement('UPDATE maintenance_tickets SET room_id_new = room_id');

            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->dropColumn('room_id');
                $table->renameColumn('room_id_new', 'room_id');
            });

            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
            $table->dropColumn('location');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE maintenance_tickets ALTER COLUMN room_id SET NOT NULL');
        }
    }
};
