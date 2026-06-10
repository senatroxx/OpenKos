<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('primary_tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();
        });

        DB::statement('INSERT INTO lease_tenant (lease_id, tenant_id, is_primary, created_at, updated_at)
            SELECT id, tenant_id, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM leases');

        DB::statement('UPDATE leases SET primary_tenant_id = (
            SELECT tenant_id FROM lease_tenant
            WHERE lease_id = leases.id AND is_primary = true
            LIMIT 1
        )');

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        DB::statement('UPDATE leases SET tenant_id = primary_tenant_id');

        DB::table('lease_tenant')->truncate();

        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['primary_tenant_id']);
            $table->dropColumn('primary_tenant_id');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });
    }
};
