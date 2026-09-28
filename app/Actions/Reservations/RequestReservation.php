<?php

namespace App\Actions\Reservations;

use App\Data\Reservation\RequestReservationData;
use App\Enums\ApplicationStatus;
use App\Enums\ReservationStatus;
use App\Models\Application;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestReservation
{
    public function execute(User $applicant, Application $application, RequestReservationData $data): Reservation
    {
        return DB::transaction(function () use ($applicant, $application, $data): Reservation {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);

            abort_unless($application->user_id === $applicant->id, 403);
            abort_unless($application->status === ApplicationStatus::Accepted, 422, __('Only accepted applications can request a reservation.'));
            abort_if(
                $application->reservations()->where('status', ReservationStatus::Converted->value)->exists(),
                422,
                __('This application has already been converted.'),
            );

            $existing = $application->reservations()
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($existing?->status === ReservationStatus::Confirmed && ! $existing->expires_at?->isFuture()) {
                $existing->update([
                    'status' => ReservationStatus::Expired,
                    'expired_at' => now(),
                ]);
                $existing = null;
            }

            abort_if($existing !== null, 422, __('This application already has an open reservation.'));

            return Reservation::query()->create([
                'application_id' => $application->id,
                'status' => ReservationStatus::Pending,
                'move_in_date' => $data->moveInDate,
            ]);
        });
    }
}
