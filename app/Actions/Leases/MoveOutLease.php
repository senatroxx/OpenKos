<?php

namespace App\Actions\Leases;

use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\Room;
use App\Results\Lease\MoveOutLeaseResult;
use Illuminate\Support\Facades\DB;

class MoveOutLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
    ) {}

    public function execute(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        return DB::transaction(function () use ($lease, $data) {
            if ($data->moveToAnotherRoom) {
                return $this->transfer($lease, $data);
            }

            return $this->terminate($lease, $data);
        });
    }

    private function terminate(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $depositRefundAmount = $data->depositReturned
            ? ($data->depositRefundAmount ?? $lease->deposit_amount)
            : null;

        $oldRoom = $lease->room;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => 'terminated',
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $oldRoom->unsetRelation('leases');

        if ($oldRoom->leases()->where('status', 'active')->doesntExist() && $oldRoom->status !== RoomStatus::Maintenance) {
            $oldRoom->update(['status' => RoomStatus::Available]);
        }

        return MoveOutLeaseResult::success($lease);
    }

    private function transfer(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $targetRoom = Room::lockForUpdate()->findOrFail($data->targetRoomId);

        abort_if($targetRoom->status === RoomStatus::Maintenance, 422, __('Target room is under maintenance.'));

        $lease->load('tenants');

        $incomingTenantIds = $lease->tenants->pluck('id')->toArray();
        $existingLease = $targetRoom->leases()->where('status', 'active')->first();

        $incomingCount = $existingLease
            ? count(array_diff($incomingTenantIds, $existingLease->tenants()->pluck('tenants.id')->all()))
            : count($incomingTenantIds);

        if (! $this->occupancy->canAccommodate($targetRoom, $incomingCount)) {
            abort(422, __('Room capacity exceeded. Target room can only hold :capacity occupants.', ['capacity' => $targetRoom->capacity]));
        }

        $depositRefundAmount = $data->depositReturned
            ? ($data->depositRefundAmount ?? $lease->deposit_amount)
            : null;

        $oldRoom = $lease->room;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => 'terminated',
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $oldRoom->unsetRelation('leases');

        if ($oldRoom->leases()->where('status', 'active')->doesntExist() && $oldRoom->status !== RoomStatus::Maintenance) {
            $oldRoom->update(['status' => RoomStatus::Available]);
        }

        $lease->load('tenants');
        $existingLease = $targetRoom->leases()->where('status', 'active')->first();

        if ($existingLease) {
            $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

            foreach ($lease->tenants as $tenant) {
                if (! $existingTenantIds->contains($tenant->id)) {
                    $existingLease->tenants()->attach($tenant->id, [
                        'is_primary' => DB::raw('false'),
                    ]);
                }
            }
        } else {
            $matchingRate = $targetRoom->rates()
                ->where('billing_interval', $lease->billing_interval)
                ->where('billing_unit', $lease->billing_unit)
                ->first();

            $newLease = $targetRoom->leases()->create([
                'primary_tenant_id' => $lease->primary_tenant_id,
                'start_date' => $data->endDate,
                'rent_amount' => $lease->rent_amount,
                'billing_interval' => $lease->billing_interval ?? 1,
                'billing_unit' => $lease->billing_unit ?? 'month',
                'is_custom_price' => $lease->is_custom_price ? DB::raw('true') : DB::raw('false'),
                'room_rate_id' => $matchingRate?->id,
                'deposit_amount' => $lease->deposit_amount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'deposit_refund_amount' => $data->carryDepositRefund ? $lease->deposit_refund_amount : null,
                'deposit_refunded_at' => $data->carryDepositRefund ? $lease->deposit_refunded_at : null,
                'rent_due_day' => $lease->rent_due_day,
                'status' => 'active',
                'notes' => 'Moved from room '.$oldRoom->name.' on '.now()->format('Y-m-d'),
            ]);

            foreach ($lease->tenants as $tenant) {
                $newLease->tenants()->attach($tenant->id, [
                    'is_primary' => $tenant->id === $lease->primary_tenant_id ? DB::raw('true') : DB::raw('false'),
                ]);
            }
        }

        $targetRoom->update(['status' => RoomStatus::Occupied]);

        return MoveOutLeaseResult::success($lease, $newLease ?? null);
    }
}
