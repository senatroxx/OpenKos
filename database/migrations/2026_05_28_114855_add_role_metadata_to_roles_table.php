<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('guard_name');
            $table->boolean('is_system')->default(false)->after('label');
            $table->boolean('is_active')->default(true)->after('is_system');
        });

        DB::statement("UPDATE roles SET is_system = true, is_active = true, label = 'Owner' WHERE name = 'owner'");
        DB::statement("UPDATE roles SET is_active = true, label = 'Admin' WHERE name = 'admin'");
        DB::statement("UPDATE roles SET is_active = true, label = 'Staff' WHERE name = 'staff'");
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'is_system', 'is_active']);
        });
    }
};
