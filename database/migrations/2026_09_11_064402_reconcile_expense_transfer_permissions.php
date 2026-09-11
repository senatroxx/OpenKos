<?php

use App\Support\PermissionRoleReconciler;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRoleReconciler::class)->reconcile();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
