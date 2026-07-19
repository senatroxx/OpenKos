<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()
            ->whereNotNull('email')
            ->whereNull('user_id')
            ->each(function (Tenant $tenant): void {
                // firstOrCreate: two tenants sharing an email, or an email that already
                // belongs to a user, must not abort the migration.
                $user = User::firstOrCreate(
                    ['email' => $tenant->email],
                    [
                        'name' => $tenant->name,
                        'password' => Hash::make(Str::password(64)),
                        'is_active' => false,
                    ],
                );

                $tenant->user()->associate($user)->save();
            });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });
    }
};
