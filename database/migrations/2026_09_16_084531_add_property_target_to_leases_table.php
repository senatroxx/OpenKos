<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('property_rate_id')->nullable()->after('unit_rate_id')->constrained()->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE leases SET property_id = units.property_id FROM units WHERE units.id = leases.unit_id');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('UPDATE leases SET property_id = (SELECT property_id FROM units WHERE units.id = leases.unit_id) WHERE unit_id IS NOT NULL');
        }

        if (DB::table('leases')->whereNull('property_id')->exists()) {
            throw new RuntimeException('Every existing lease must have a property lineage before this migration can continue.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leases ALTER COLUMN property_id SET NOT NULL');
            DB::statement('ALTER TABLE leases ALTER COLUMN unit_id DROP NOT NULL');
            DB::statement('ALTER TABLE leases ADD CONSTRAINT leases_target_rate_check CHECK ((unit_id IS NOT NULL AND property_rate_id IS NULL) OR (unit_id IS NULL AND property_rate_id IS NOT NULL AND unit_rate_id IS NULL))');
        } else {
            Schema::table('leases', function (Blueprint $table): void {
                $table->unsignedBigInteger('property_id')->nullable(false)->change();
                $table->unsignedBigInteger('unit_id')->nullable()->change();
            });
        }

        Schema::table('units', function (Blueprint $table): void {
            $table->unique(['id', 'property_id'], 'units_id_property_unique');
        });

        Schema::table('property_rates', function (Blueprint $table): void {
            $table->unique(['id', 'property_id'], 'property_rates_id_property_unique');
        });

        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->unique(['id', 'unit_id'], 'unit_rates_id_unit_unique');
        });

        Schema::table('leases', function (Blueprint $table): void {
            $table->foreign(['unit_id', 'property_id'], 'leases_unit_property_fk')
                ->references(['id', 'property_id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['property_rate_id', 'property_id'], 'leases_property_rate_property_fk')
                ->references(['id', 'property_id'])
                ->on('property_rates')
                ->restrictOnDelete();
            $table->foreign(['unit_rate_id', 'unit_id'], 'leases_unit_rate_unit_fk')
                ->references(['id', 'unit_id'])
                ->on('unit_rates')
                ->restrictOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX leases_active_whole_property_unique ON leases (property_id) WHERE unit_id IS NULL AND status = 'active' AND deleted_at IS NULL");

        Schema::table('leases', function (Blueprint $table): void {
            $table->index(['property_id', 'status'], 'leases_property_status_index');
            $table->index(['property_id', 'property_rate_id'], 'leases_property_rate_index');
        });
    }

    public function down(): void
    {
        if (DB::table('leases')->whereNull('unit_id')->exists()) {
            throw new RuntimeException('Refusing to roll back lease property targets while whole-property leases exist.');
        }

        DB::statement('DROP INDEX IF EXISTS leases_active_whole_property_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leases DROP CONSTRAINT IF EXISTS leases_target_rate_check');
            DB::statement('ALTER TABLE leases ALTER COLUMN unit_id SET NOT NULL');
        } else {
            Schema::table('leases', function (Blueprint $table): void {
                $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            });
        }

        Schema::table('leases', function (Blueprint $table): void {
            $table->dropForeign('leases_unit_property_fk');
            $table->dropForeign('leases_property_rate_property_fk');
            $table->dropForeign('leases_unit_rate_unit_fk');
            $table->dropIndex('leases_property_status_index');
            $table->dropIndex('leases_property_rate_index');
            $table->dropForeign(['property_rate_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['property_rate_id', 'property_id']);
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropUnique('units_id_property_unique');
        });

        Schema::table('property_rates', function (Blueprint $table): void {
            $table->dropUnique('property_rates_id_property_unique');
        });

        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->dropUnique('unit_rates_id_unit_unique');
        });
    }
};
