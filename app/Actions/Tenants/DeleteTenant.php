<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Results\Tenant\DeleteTenantResult;
use Illuminate\Support\Facades\DB;

final class DeleteTenant
{
    public function execute(Tenant $tenant): DeleteTenantResult
    {
        return DB::transaction(function () use ($tenant): DeleteTenantResult {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if ($lockedTenant->leases()->active()->exists()) {
                return DeleteTenantResult::blocked();
            }

            $lockedTenant->delete();

            return DeleteTenantResult::success();
        });
    }
}
