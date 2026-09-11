<?php

use App\Support\PermissionRoleReconciler;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRoleReconciler::class)->reconcile();
    }

    public function down(): void {}
};
