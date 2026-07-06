<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('operation'); // create, update, delete, restore
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->nullableMorphs('actor'); // actor_type + actor_id
            $table->string('source')->default('UI'); // UI, API, Plugin, Scheduler, System
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['operation', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
