<?php

namespace App\Repositories;

use App\Enums\UnitStatus;
use App\Models\Application;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Unit;
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

    public function unitHasCapacityForReservation(Unit $unit, string $moveInDate): bool
    {
        if (in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true)) {
            return false;
        }

        if ($this->unitHasIncompatibleActiveLeaseStart($unit, $moveInDate)
            || $this->hasLeaseConflictForReservation($unit, $moveInDate)
            || $this->hasWholePropertyReservation($unit->property_id)) {
            return false;
        }

        $activeOccupants = $this->occupancy->activeOccupantCountAtDate($unit, $moveInDate);
        $reservedSlots = $this->unitReservationCountOverlappingLease($unit, null);

        return $activeOccupants + $reservedSlots < $unit->capacity;
    }

    public function propertyHasCapacityForReservation(Property $property, string $moveInDate): bool
    {
        return ! $this->hasLeaseConflictForReservation($property, $moveInDate)
            && ! $this->hasAnyReservationForProperty($property);
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

    /** @return Collection<int, Unit> */
    public function availableUnitsFor(Application $application, string $moveInDate): Collection
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
            ->get()
            ->filter(fn (Unit $unit): bool => (int) $unit->occupied_slots + (int) $unit->reserved_slots < $unit->capacity)
            ->values();
    }
}
