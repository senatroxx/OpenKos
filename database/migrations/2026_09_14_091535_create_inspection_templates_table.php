<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspection_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('inspection_type', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['inspection_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_templates');
    }
};
