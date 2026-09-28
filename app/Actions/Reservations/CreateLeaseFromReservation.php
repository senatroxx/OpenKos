<?php

namespace App\Actions\Reservations;

use App\Actions\Leases\CreateLease as CreateLeaseAction;
use App\Data\Lease\CreateLeaseData;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use App\Enums\ReservationStatus;
use App\Models\Application;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateLeaseFromReservation
{
    public function __construct(private CreateLeaseAction $createLease) {}

    public function execute(User $operator, Reservation $reservation, CreateLeaseData $data): Lease
    {
        $application = Application::query()->findOrFail($reservation->application_id);
        $unitId = $reservation->unit_id;

        return DB::transaction(function () use ($operator, $reservation, $data, $application, $unitId): Lease {
            $property = Property::query()->lockForUpdate()->findOrFail($application->property_id);
            $unit = $unitId === null ? null : Unit::query()->lockForUpdate()->findOrFail($unitId);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $application = Application::query()->lockForUpdate()->findOrFail($reservation->application_id);

            abort_unless($reservation->status === ReservationStatus::Confirmed, 422, __('Only confirmed reservations can create a lease.'));
            abort_unless($reservation->expires_at?->isFuture(), 422, __('This reservation has expired.'));
            abort_unless($application->status === ApplicationStatus::Accepted, 422, __('Only accepted applications can create a lease.'));

            if ($application->target_type === ApplicationTargetType::WholeProperty) {
                abort_unless($unit === null && $reservation->unit_id === null, 422, __('This reservation no longer matches its property target.'));
                $target = $property;
            } else {
                abort_unless($unit !== null && $unit->property_id === $property->id && $unit->unit_type_id === $application->unit_type_id, 422, __('This reservation no longer matches its unit target.'));
                $target = $unit;
            }

            $user = User::query()->lockForUpdate()->findOrFail($application->user_id);
            $tenant = Tenant::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($application->converted_tenant_id !== null) {
                abort_unless($tenant !== null && $tenant->id === $application->converted_tenant_id, 422, __('The recorded Tenant no longer exists. Resolve it before creating a lease.'));
            }

            abort_if($tenant?->trashed() || ($tenant !== null && ! $tenant->is_active), 422, __('This user has an archived or inactive Tenant record. Resolve it before creating a lease.'));

            $tenant ??= Tenant::query()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'id_card_number' => $user->id_card_number,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'is_active' => true,
            ]);

            $lease = $this->createLease->execute($target, $data->withTenantIds([$tenant->id]), $reservation->id);

            $reservation->update([
                'lease_id' => $lease->id,
                'status' => ReservationStatus::Converted,
                'converted_at' => now(),
                'converted_by' => $operator->id,
            ]);
            $application->update([
                'converted_tenant_id' => $tenant->id,
                'converted_at' => now(),
            ]);

            return $lease;
        });
    }
}
