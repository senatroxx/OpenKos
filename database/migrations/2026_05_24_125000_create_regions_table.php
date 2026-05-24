<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2);
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
