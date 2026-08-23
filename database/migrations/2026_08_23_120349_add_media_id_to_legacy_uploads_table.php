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
        foreach (['tenant_documents', 'payment_proofs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('media_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('media')
                    ->restrictOnDelete();
                $table->index('media_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['tenant_documents', 'payment_proofs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['media_id']);
                $table->dropIndex(['media_id']);
                $table->dropColumn('media_id');
            });
        }
    }
};
