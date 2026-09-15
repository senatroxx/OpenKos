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
        Schema::create('inspection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('condition', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
