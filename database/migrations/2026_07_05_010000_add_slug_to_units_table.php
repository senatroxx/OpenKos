<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Slug for future public property/unit listing URLs
 * (/properties/{property-slug}/units/{unit-slug}). Unique per property, since
 * unit names (e.g. "A1") repeat across properties. Current routing still uses
 * the numeric id — this is future-proofing only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill unique-per-property slugs from existing names (incl. trashed).
        $used = [];
        foreach (DB::table('units')->orderBy('id')->get(['id', 'property_id', 'name']) as $row) {
            $base = Str::slug($row->name) ?: 'unit';
            $slug = $base;
            $counter = 1;
            while (in_array($slug, $used[$row->property_id] ?? [], true)) {
                $slug = $base.'-'.++$counter;
            }
            $used[$row->property_id][] = $slug;
            DB::table('units')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('units', function (Blueprint $table) {
            $table->unique(['property_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
