<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Results\Tenant\DeleteTenantResult;
use Illuminate\Support\Facades\DB;

final class DeleteTenant
{
    public function execute(Tenant $tenant): DeleteTenantResult
    {
        // ponytail: locking the tenant row serializes with other tenant-row
        // locks but not with CreateLease::execute, which locks the unit.
        // A concurrent lease assignment between the exists() check and
        // delete() could leave an archived tenant on an active lease.
        // Fixing this would require CreateLease to also lock tenant rows.
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
