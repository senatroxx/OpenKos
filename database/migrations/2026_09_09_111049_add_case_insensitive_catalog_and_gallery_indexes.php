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
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_owner_property_id_name_unique');
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->dropUnique('unit_types_property_id_name_unique');
        });

        match (DB::getDriverName()) {
            'pgsql', 'sqlite' => $this->createExpressionIndexes(),
            'mysql', 'mariadb' => $this->createGeneratedColumnIndexes(),
            default => $this->createPortableFallbackIndexes(),
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql', 'sqlite' => $this->dropExpressionIndexes(),
            'mysql', 'mariadb' => $this->dropGeneratedColumnIndexes(),
            default => $this->dropPortableFallbackIndexes(),
        };

        Schema::table('amenities', function (Blueprint $table): void {
            $table->unique(['owner_property_id', 'name']);
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->unique(['property_id', 'name']);
        });
    }

    private function createExpressionIndexes(): void
    {
        DB::statement('CREATE UNIQUE INDEX amenities_global_lower_name_unique ON amenities (LOWER(name)) WHERE owner_property_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX amenities_property_lower_name_unique ON amenities (owner_property_id, LOWER(name)) WHERE owner_property_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX unit_types_property_lower_name_unique ON unit_types (property_id, LOWER(name))');
        DB::statement("CREATE UNIQUE INDEX media_gallery_owner_position_unique ON media (mediable_type, mediable_id, position) WHERE collection = 'photos'");
    }

    private function dropExpressionIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS amenities_global_lower_name_unique');
        DB::statement('DROP INDEX IF EXISTS amenities_property_lower_name_unique');
        DB::statement('DROP INDEX IF EXISTS unit_types_property_lower_name_unique');
        DB::statement('DROP INDEX IF EXISTS media_gallery_owner_position_unique');
    }

    private function createGeneratedColumnIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->string('name_normalized')->storedAs('LOWER(name)');
            $table->string('global_name_normalized')->nullable()->storedAs('IF(owner_property_id IS NULL, LOWER(name), NULL)');
            $table->unique(['owner_property_id', 'name_normalized'], 'amenities_property_name_normalized_unique');
            $table->unique('global_name_normalized', 'amenities_global_name_normalized_unique');
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->string('name_normalized')->storedAs('LOWER(name)');
            $table->unique(['property_id', 'name_normalized'], 'unit_types_property_name_normalized_unique');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->string('gallery_position_key')->nullable()->storedAs("IF(collection = 'photos', CONCAT(mediable_type, ':', mediable_id, ':', position), NULL)");
            $table->unique('gallery_position_key', 'media_gallery_position_key_unique');
        });
    }

    private function dropGeneratedColumnIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_property_name_normalized_unique');
            $table->dropUnique('amenities_global_name_normalized_unique');
            $table->dropColumn(['name_normalized', 'global_name_normalized']);
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->dropUnique('unit_types_property_name_normalized_unique');
            $table->dropColumn('name_normalized');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_gallery_position_key_unique');
            $table->dropColumn('gallery_position_key');
        });
    }

    private function createPortableFallbackIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->unique(['owner_property_id', 'name']);
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->unique(['property_id', 'name']);
        });
    }

    private function dropPortableFallbackIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_owner_property_id_name_unique');
        });

        Schema::table('unit_types', function (Blueprint $table): void {
            $table->dropUnique('unit_types_property_id_name_unique');
        });
    }
};
