<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('previous_lease_id')
                ->nullable()
                ->after('id')
                ->constrained('leases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_lease_id');
        });
    }
};
