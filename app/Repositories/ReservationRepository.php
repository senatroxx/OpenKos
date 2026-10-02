<?php

namespace App\Repositories;

use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Models\Application;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationRepository
{
    public function __construct(private OccupancyRepository $occupancy) {}

    public function hasLeaseConflictForReservation(Property|Unit $target, string $moveInDate): bool
    {
        return Lease::query()
            ->activeConflictsForTarget($target, ! ($target instanceof Unit))
            ->where(function (Builder $query) use ($moveInDate): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $moveInDate);
            })
            ->exists();
    }

    public function unitHasIncompatibleActiveLeaseStart(Unit $unit, string $moveInDate): bool
    {
        return $unit->leases()
            ->active()
            ->whereDate('start_date', '!=', $moveInDate)
            ->exists();
    }

    public function propertyHasAnyActiveLease(Property $property): bool
    {
        return Lease::query()->activeConflictsForTarget($property)->exists();
    }

    public function hasWholePropertyReservation(Property|int $property): bool
    {
        return Reservation::query()
            ->holding()
            ->forProperty($property)
            ->whereNull('unit_id')
            ->exists();
    }

    public function hasAnyReservationForProperty(Property $property): bool
    {
        return Reservation::query()->holding()->forProperty($property)->exists();
    }

    /** @return array{occupied_slots: int, reserved_slots: int} */
    public function unitReservationOccupancy(Unit $unit, string $moveInDate): array
    {
        return [
            'occupied_slots' => $this->occupancy->activeOccupantCountAtDate($unit, $moveInDate),
            'reserved_slots' => $this->unitReservationCountOverlappingLease($unit, null),
        ];
    }

    public function hasReservationConflictForLease(Property|Unit $target, ?string $leaseEndDate, ?int $exceptReservationId = null): bool
    {
        $query = Reservation::query()
            ->holding()
            ->forProperty($target instanceof Unit ? $target->property_id : $target)
            ->overlappingLeasePeriod($leaseEndDate)
            ->when($exceptReservationId !== null, fn (Builder $query) => $query->whereKeyNot($exceptReservationId));

        if ($target instanceof Unit) {
            $query->whereNull('unit_id');
        }

        return $query->exists();
    }

    public function unitReservationCountOverlappingLease(Unit $unit, ?string $leaseEndDate, ?int $exceptReservationId = null): int
    {
        return Reservation::query()
            ->holding()
            ->where('unit_id', $unit->id)
            ->overlappingLeasePeriod($leaseEndDate)
            ->when($exceptReservationId !== null, fn (Builder $query) => $query->whereKeyNot($exceptReservationId))
            ->count();
    }

    /** @return Collection<int, Reservation> */
    public function confirmedReservationsExpiredBy(CarbonInterface $expiresAt): Collection
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('expires_at', '<=', $expiresAt)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @return Collection<int, Unit> */
    public function unitCandidatesForReservation(Application $application, string $moveInDate): Collection
    {
        if ($application->unit_type_id === null) {
            return collect();
        }

        $occupiedSlots = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->selectRaw('COUNT(*)')
            ->whereColumn('leases.unit_id', 'units.id')
            ->where('leases.status', 'active')
            ->where(function (QueryBuilder $query) use ($moveInDate): void {
                $query->whereNull('leases.end_date')->orWhereDate('leases.end_date', '>=', $moveInDate);
            });

        $reservedSlots = Reservation::query()
            ->selectRaw('COUNT(*)')
            ->holding()
            ->whereColumn('unit_id', 'units.id');

        return Unit::query()
            ->where('property_id', $application->property_id)
            ->where('unit_type_id', $application->unit_type_id)
            ->whereNotIn('status', [UnitStatus::Maintenance->value, UnitStatus::Unavailable->value])
            ->whereDoesntHave('leases', fn (Builder $leases) => $leases
                ->active()
                ->whereDate('start_date', '!=', $moveInDate))
            ->whereDoesntHave('property', fn (Builder $query) => $query->whereHas('leases', fn (Builder $leases) => $leases
                ->active()
                ->whereNull('unit_id')
                ->where(fn (Builder $leases) => $leases->whereNull('end_date')->orWhereDate('end_date', '>=', $moveInDate))))
            ->whereDoesntHave('property', fn (Builder $query) => $query->whereHas('reservations', fn (Builder $reservations) => $reservations
                ->holding()
                ->whereNull('unit_id')))
            ->select(['id', 'property_id', 'unit_type_id', 'name', 'slug', 'capacity', 'status'])
            ->selectSub($occupiedSlots, 'occupied_slots')
            ->selectSub($reservedSlots, 'reserved_slots')
            ->orderBy('name')
            ->get();
    }
}
