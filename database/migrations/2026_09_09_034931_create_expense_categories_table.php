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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $defaults = [
            'maintenance' => 'Maintenance',
            'utilities' => 'Utilities',
            'repairs' => 'Repairs',
            'supplies' => 'Supplies',
            'insurance' => 'Insurance',
            'taxes' => 'Taxes',
            'cleaning' => 'Cleaning',
            'other' => 'Other',
        ];

        $sortOrder = 0;

        foreach ($defaults as $slug => $label) {
            DB::table('expense_categories')->insertOrIgnore([
                'slug' => $slug,
                'label' => $label,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sortOrder++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
