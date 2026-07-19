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
                // users.email and tenants.user_id are both unique (1:1), so an email
                // that's already taken — by another tenant's backfilled user or by an
                // existing account — can only back one tenant. Link the first; leave the
                // rest unlinked (user_id null) rather than abort or hijack an account.
                if (User::where('email', $tenant->email)->exists()) {
                    return;
                }

                $user = User::create([
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'password' => Hash::make(Str::password(64)),
                    'is_active' => false,
                ]);

                $tenant->user()->associate($user)->save();
            });

        // Tripwire: dropping email is irreversible. Nothing populated tenants.user_id
        // before this feature, so already-linked rows shouldn't exist — but if one has
        // a contact email that differs from its user's (unique, NOT NULL) login email,
        // dropping would silently destroy it. user.email can't be safely overwritten
        // (it's the login identity), so surface it for manual resolution instead.
        $orphanedEmails = Tenant::query()
            ->whereNotNull('user_id')
            ->whereNotNull('email')
            ->with('user:id,email')
            ->get()
            ->filter(fn (Tenant $tenant): bool => $tenant->email !== $tenant->user?->email);

        if ($orphanedEmails->isNotEmpty()) {
            throw new RuntimeException(
                "Refusing to drop tenants.email: {$orphanedEmails->count()} linked tenant(s) have a contact "
                .'email that differs from their user login email (ids: '.$orphanedEmails->pluck('id')->join(', ')
                .'). Reconcile these before migrating.'
            );
        }

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
