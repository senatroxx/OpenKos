<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateLeaseFromReservation as CreateLeaseFromReservationAction;
use App\Actions\Reservations\RejectReservation;
use App\Actions\Reservations\RequestReservation;
use App\Events\Lease\LeaseCreated;
use App\Events\Unit\UnitStatusChanged;
use App\Http\Requests\Reservation\ConfirmReservationRequest;
use App\Http\Requests\Reservation\CreateLeaseFromReservationRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Application;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Application $application, RequestReservation $action): RedirectResponse
    {
        $this->authorize('requestReservation', $application);
        $action->execute($request->user(), $application, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation requested.')]);

        return to_route('applications.show', $application);
    }

    public function confirm(ConfirmReservationRequest $request, Reservation $reservation, ConfirmReservation $action): RedirectResponse
    {
        $this->authorize('confirm', $reservation);
        $action->execute($request->user(), $reservation, $request->unitId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation confirmed.')]);

        return back();
    }

    public function reject(Request $request, Reservation $reservation, RejectReservation $action): RedirectResponse
    {
        $this->authorize('reject', $reservation);
        $action->execute($request->user(), $reservation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation rejected.')]);

        return back();
    }

    public function cancel(Request $request, Reservation $reservation, CancelReservation $action): RedirectResponse
    {
        $this->authorize('cancel', $reservation);
        $action->execute($request->user(), $reservation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation cancelled.')]);

        return back();
    }

    public function createLease(
        CreateLeaseFromReservationRequest $request,
        Reservation $reservation,
        CreateLeaseFromReservationAction $action,
    ): RedirectResponse {
        $this->authorize('createLease', $reservation);

        $unit = $reservation->unit;
        $oldUnitStatus = $unit?->status;
        $lease = $action->execute($request->user(), $reservation, $request->toData());
        $lease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

        if ($lease->wasRecentlyCreated) {
            LeaseCreated::dispatch($lease, $lease->tenants->pluck('id')->toArray(), actorId: Auth::id());
        }

        if ($unit !== null) {
            $unit->refresh();
            if ($oldUnitStatus !== $unit->status) {
                UnitStatusChanged::dispatch($unit, $oldUnitStatus, $unit->status, actorId: Auth::id());
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease created from reservation.')]);

        return back();
    }
}
