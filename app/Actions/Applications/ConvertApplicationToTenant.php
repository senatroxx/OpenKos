<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Results\Application\ApplicationResult;
use Illuminate\Support\Facades\DB;

final class ConvertApplicationToTenant
{
    public function execute(User $actor, Application $application): ApplicationResult
    {
        if (! $actor->isOwner() && ! $actor->can('tenants.view')) {
            return ApplicationResult::error(__('You cannot convert applications.'));
        }

        return DB::transaction(function () use ($application): ApplicationResult {
            $lockedApplication = Application::query()->lockForUpdate()->findOrFail($application->id);
            if ($lockedApplication->status->value !== 'accepted') {
                return ApplicationResult::error(__('Only accepted applications can be converted.'));
            }

            $user = User::query()->lockForUpdate()->findOrFail($lockedApplication->user_id);
            $tenant = Tenant::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($lockedApplication->converted_tenant_id !== null) {
                return $tenant === null
                    ? ApplicationResult::error(__('The recorded Tenant no longer exists. Resolve it before conversion.'))
                    : ApplicationResult::success($tenant);
            }

            if ($tenant?->trashed() || ($tenant !== null && ! $tenant->is_active)) {
                return ApplicationResult::error(__('This user has an archived or inactive Tenant record. Resolve it before conversion.'));
            }

            $tenant ??= Tenant::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'id_card_number' => $user->id_card_number,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'is_active' => true,
            ]);

            $lockedApplication->update([
                'converted_tenant_id' => $tenant->id,
                'converted_at' => now(),
            ]);

            return ApplicationResult::success($tenant);
        });
    }
}
