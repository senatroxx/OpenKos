<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS leases_active_whole_property_unique');
        DB::statement("CREATE UNIQUE INDEX leases_active_whole_property_unique ON leases (property_id) WHERE unit_id IS NULL AND status = 'active' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS leases_active_whole_property_unique');
    }
};
