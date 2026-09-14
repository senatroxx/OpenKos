<?php

use App\Enums\AmenityScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        DB::transaction(function (): void {
            $this->consolidateAmenities();
            $this->assignSlugs();
        });

        $this->dropLegacyIndexes();

        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropForeign(['owner_property_id']);
            $table->dropColumn('owner_property_id');
        });

        Schema::table('amenities', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug', 'amenities_slug_unique');
            $table->index(['scope', 'is_active', 'name'], 'amenities_scope_is_active_name_index');
        });

        $this->createTrimmedNameIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('The amenity normalization migration is forward-only because it consolidates data.');
    }

    private function consolidateAmenities(): void
    {
        $canonicalIds = [];

        DB::table('amenities')->orderBy('id')->get()->each(function (object $amenity) use (&$canonicalIds): void {
            $name = trim((string) $amenity->name);

            if ($name === '') {
                throw new RuntimeException("Amenity {$amenity->id} has an empty name and cannot be normalized.");
            }

            $key = mb_strtolower($name);

            if (! isset($canonicalIds[$key])) {
                $canonicalIds[$key] = (int) $amenity->id;

                DB::table('amenities')
                    ->where('id', $amenity->id)
                    ->update(['name' => $name]);

                return;
            }

            $canonicalId = $canonicalIds[$key];
            $canonical = DB::table('amenities')->where('id', $canonicalId)->first();

            DB::table('amenities')
                ->where('id', $canonicalId)
                ->update([
                    'scope' => $this->mergedScope($canonical->scope, $amenity->scope)->value,
                    'is_active' => (bool) $canonical->is_active || (bool) $amenity->is_active,
                ]);

            $this->mergePivot('amenity_property', 'property_id', $canonicalId, (int) $amenity->id);
            $this->mergePivot('amenity_unit_type', 'unit_type_id', $canonicalId, (int) $amenity->id);

            DB::table('amenities')->where('id', $amenity->id)->delete();
        });
    }

    private function mergePivot(string $table, string $resourceKey, int $canonicalId, int $duplicateId): void
    {
        DB::table($table)
            ->where('amenity_id', $duplicateId)
            ->orderBy($resourceKey)
            ->get([$resourceKey])
            ->each(function (object $pivot) use ($table, $resourceKey, $canonicalId, $duplicateId): void {
                $exists = DB::table($table)
                    ->where('amenity_id', $canonicalId)
                    ->where($resourceKey, $pivot->{$resourceKey})
                    ->exists();

                $duplicate = DB::table($table)
                    ->where('amenity_id', $duplicateId)
                    ->where($resourceKey, $pivot->{$resourceKey});

                if ($exists) {
                    $duplicate->delete();

                    return;
                }

                $duplicate->update(['amenity_id' => $canonicalId]);
            });
    }

    private function assignSlugs(): void
    {
        $usedSlugs = [];

        DB::table('amenities')->orderBy('id')->get(['id', 'name'])->each(function (object $amenity) use (&$usedSlugs): void {
            $base = Str::slug((string) $amenity->name) ?: "amenity-{$amenity->id}";
            $slug = $base;
            $counter = 1;

            while (isset($usedSlugs[$slug])) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $usedSlugs[$slug] = true;

            DB::table('amenities')->where('id', $amenity->id)->update(['slug' => $slug]);
        });
    }

    private function mergedScope(string $left, string $right): AmenityScope
    {
        $leftScope = AmenityScope::tryFrom($left) ?? AmenityScope::Both;
        $rightScope = AmenityScope::tryFrom($right) ?? AmenityScope::Both;

        if ($leftScope === $rightScope) {
            return $leftScope;
        }

        return AmenityScope::Both;
    }

    private function dropLegacyIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropIndex('amenities_owner_property_id_is_active_name_index');
            $table->dropIndex('amenities_scope_is_active_name_index');
        });

        match (DB::getDriverName()) {
            'pgsql', 'sqlite' => $this->dropExpressionIndexes(),
            'mysql', 'mariadb' => $this->dropGeneratedColumnIndexes(),
            default => $this->dropPortableIndexes(),
        };
    }

    private function dropExpressionIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS amenities_global_lower_name_unique');
        DB::statement('DROP INDEX IF EXISTS amenities_property_lower_name_unique');
    }

    private function dropGeneratedColumnIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_property_name_normalized_unique');
            $table->dropUnique('amenities_global_name_normalized_unique');
            $table->dropColumn(['name_normalized', 'global_name_normalized']);
        });
    }

    private function dropPortableIndexes(): void
    {
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_owner_property_id_name_unique');
        });
    }

    private function createTrimmedNameIndex(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('CREATE UNIQUE INDEX amenities_trimmed_lower_name_unique ON amenities (LOWER(BTRIM(name)))'),
            'sqlite' => DB::statement('CREATE UNIQUE INDEX amenities_trimmed_lower_name_unique ON amenities (LOWER(TRIM(name)))'),
            'mysql', 'mariadb' => Schema::table('amenities', function (Blueprint $table): void {
                $table->string('name_normalized')->storedAs('LOWER(TRIM(name))');
                $table->unique('name_normalized', 'amenities_trimmed_lower_name_unique');
            }),
            'sqlsrv' => Schema::table('amenities', function (Blueprint $table): void {
                $table->computed('name_normalized', 'LOWER(LTRIM(RTRIM([name])))');
                $table->unique('name_normalized', 'amenities_trimmed_lower_name_unique');
            }),
            default => throw new LogicException('The amenity catalog migration does not support this database driver.'),
        };
    }
};
