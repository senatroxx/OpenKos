<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();   // stable identifier, stored on properties.type
            $table->string('label');            // editable display name
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the starter set. Admins add locale-specific types (kos, riad, machiya, …) themselves.
        $now = now();
        $defaults = ['boarding_house' => 'Boarding House', 'apartment' => 'Apartment', 'villa' => 'Villa', 'hostel' => 'Hostel', 'hotel' => 'Hotel'];
        $rows = [];
        $order = 0;
        foreach ($defaults as $slug => $label) {
            $rows[] = [
                'slug' => $slug,
                'label' => $label,
                'is_active' => true,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('property_types')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
    }
};
