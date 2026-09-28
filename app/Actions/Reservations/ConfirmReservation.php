<?php

namespace App\Actions\Reservations;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationTargetType;
use App\Enums\ReservationStatus;
use App\Models\Application;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\ReservationRepository;
use Illuminate\Support\Facades\DB;

class ConfirmReservation
{
    public function __construct(private ReservationRepository $reservations) {}

    public function execute(User $operator, Reservation $reservation, ?int $unitId): Reservation
    {
        $applicationId = $reservation->application_id;
        $propertyId = Application::query()->whereKey($applicationId)->value('property_id');

        return DB::transaction(function () use ($operator, $reservation, $unitId, $applicationId, $propertyId): Reservation {
            $property = Property::query()->lockForUpdate()->findOrFail($propertyId);
            $unit = $unitId === null ? null : Unit::query()->lockForUpdate()->findOrFail($unitId);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $application = Application::query()->lockForUpdate()->findOrFail($applicationId);

            abort_unless($application->status === ApplicationStatus::Accepted, 422, __('Only accepted applications can have a confirmed reservation.'));
            abort_if($application->converted_at !== null || $application->converted_tenant_id !== null, 422, __('This application has already been converted.'));
            abort_unless($reservation->status === ReservationStatus::Pending, 422, __('Only pending reservations can be confirmed.'));

            if ($application->target_type === ApplicationTargetType::WholeProperty) {
                abort_if($unit !== null, 422, __('Whole-property reservations do not select a unit.'));
                abort_unless($property->rental_mode->supportsWholePropertyRental(), 422, __('This property no longer supports whole-property rentals.'));
                abort_unless(
                    ! $this->reservations->propertyHasAnyActiveLease($property),
                    422,
                    __('Close all active leases on this property before confirming a whole-property reservation.'),
                );
                abort_unless($this->reservations->propertyHasCapacityForReservation($property, $reservation->move_in_date->toDateString()), 422, __('The property is no longer available for these dates.'));
            } else {
                abort_unless($property->rental_mode->supportsUnitInventory(), 422, __('This property no longer supports unit rentals.'));
                abort_unless($unit !== null, 422, __('Select a unit to confirm this reservation.'));
                abort_unless(
                    $unit->property_id === $property->id && $unit->unit_type_id === $application->unit_type_id,
                    422,
                    __('Select an available unit of the applied-for type.'),
                );
                abort_unless(
                    ! $this->reservations->unitHasIncompatibleActiveLeaseStart($unit, $reservation->move_in_date->toDateString()),
                    422,
                    __('The selected unit has an active lease with a different start date.'),
                );
                abort_unless($this->reservations->unitHasCapacityForReservation($unit, $reservation->move_in_date->toDateString()), 422, __('The selected unit has no remaining capacity for these dates.'));
            }

            $holdHours = max(1, (int) Setting::get('reservation_hold_hours'));
            $reservation->update([
                'unit_id' => $unit?->id,
                'status' => ReservationStatus::Confirmed,
                'confirmed_at' => now(),
                'expires_at' => now()->addHours($holdHours),
                'confirmed_by' => $operator->id,
            ]);

            return $reservation->fresh();
        });
    }
}
