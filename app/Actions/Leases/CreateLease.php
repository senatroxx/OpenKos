<?php

namespace App\Actions\Leases;

use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\CreateLeaseData;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomRate;
use Illuminate\Support\Facades\DB;

class CreateLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
    ) {}

    public function execute(Room $room, CreateLeaseData $data): mixed
    {
        $tenantIds = array_values(array_unique($data->tenantIds));

        return DB::transaction(function () use ($room, $data, $tenantIds) {
            $room = Room::lockForUpdate()->findOrFail($room->id);

            $existingLease = $room->leases()->where('status', 'active')->first();
            $activeTenantsCount = $this->occupancy->activeOccupantCount($room);

            if ($existingLease) {
                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');
                $newTenantIds = array_diff($tenantIds, $existingTenantIds->all());

                abort_if(! $this->occupancy->canAccommodate($room, count($newTenantIds)), 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));

                foreach ($newTenantIds as $tenantId) {
                    $existingLease->tenants()->attach($tenantId, ['is_primary' => DB::raw('false')]);
                }

                $room->update(['status' => RoomStatus::Occupied]);

                return $existingLease;
            }

            abort_if(! $this->occupancy->canAccommodate($room, count($tenantIds)), 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));

            $roomRate = $data->roomRateId ? RoomRate::find($data->roomRateId) : null;
            $rentAmount = $data->rentAmount ?? $roomRate?->amount ?? $room->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount');
            $isCustomPrice = $data->rentAmount !== null && $roomRate && (float) $data->rentAmount !== (float) $roomRate->amount;

            $primaryTenantId = $tenantIds[0];

            $lease = $room->leases()->create([
                'primary_tenant_id' => $primaryTenantId,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'rent_amount' => $rentAmount,
                'billing_interval' => $data->billingInterval ?? $roomRate?->billing_interval ?? 1,
                'billing_unit' => $data->billingUnit ?? $roomRate?->billing_unit ?? 'month',
                'is_custom_price' => $isCustomPrice ? DB::raw('true') : DB::raw('false'),
                'room_rate_id' => $data->roomRateId,
                'deposit_amount' => $data->depositAmount ?? 0,
                'deposit_paid_at' => $data->depositPaidAt,
                'deposit_refund_amount' => $data->depositRefundAmount,
                'deposit_refunded_at' => $data->depositRefundedAt,
                'rent_due_day' => $data->rentDueDay ?? 1,
                'status' => 'active',
                'notes' => $data->notes,
            ]);

            foreach ($tenantIds as $index => $tenantId) {
                $lease->tenants()->attach($tenantId, ['is_primary' => $index === 0 ? DB::raw('true') : DB::raw('false')]);
            }

            $room->update(['status' => RoomStatus::Occupied]);

            return $lease;
        });
    }
}
