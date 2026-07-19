<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DisableTenantAccess
{
    public function execute(Tenant $tenant): void
    {
        $user = $tenant->user;

        if (! $user) {
            return;
        }

        $user->update([
            'is_active' => false,
            'invited_at' => null,
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
