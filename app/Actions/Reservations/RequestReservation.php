<?php

namespace App\Actions\Reservations;

use App\Business\Reservations\ReservationTransitionValidator;
use App\Data\Reservation\RequestReservationData;
use App\Enums\ApplicationStatus;
use App\Enums\ReservationStatus;
use App\Models\Application;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reservation mutations lock Application → Reservation → Property → Unit,
 * omitting records they do not need.
 */
class RequestReservation
{
    public function __construct(private ReservationTransitionValidator $transitions) {}

    public function execute(User $applicant, Application $application, RequestReservationData $data): Reservation
    {
        return DB::transaction(function () use ($applicant, $application, $data): Reservation {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);

            abort_unless($application->user_id === $applicant->id, 403);
            abort_unless($application->status === ApplicationStatus::Accepted, 422, __('Only accepted applications can request a reservation.'));
            abort_if($application->converted_at !== null || $application->converted_tenant_id !== null, 422, __('This application has already been converted.'));
            abort_if(
                $application->reservations()->where('status', ReservationStatus::Converted->value)->exists(),
                422,
                __('This application has already been converted.'),
            );

            $existingReservations = $application->reservations()
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $hasOpenReservation = $existingReservations->contains(
                fn (Reservation $existing): bool => $existing->status === ReservationStatus::Pending
                    || ($existing->status === ReservationStatus::Confirmed && ! $existing->isExpired()),
            );

            abort_if($hasOpenReservation, 422, __('This application already has an open reservation.'));

            foreach ($existingReservations as $existing) {
                if ($existing->status !== ReservationStatus::Confirmed || ! $existing->isExpired()) {
                    continue;
                }

                abort_unless($this->transitions->canTransition($existing->status, ReservationStatus::Expired), 422, __('This reservation cannot be expired.'));
                $existing->update([
                    'status' => ReservationStatus::Expired,
                    'expired_at' => now(),
                ]);
            }

            return Reservation::query()->create([
                'application_id' => $application->id,
                'status' => ReservationStatus::Pending,
                'move_in_date' => $data->moveInDate,
            ]);
        });
    }
}
