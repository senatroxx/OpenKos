<?php

namespace App\Actions\Leases;

use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\CreateLeaseData;
use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Models\UnitRate;
use Illuminate\Support\Facades\DB;

class CreateLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
    ) {}

    public function execute(Unit $unit, CreateLeaseData $data): mixed
    {
        $tenantIds = array_values(array_unique($data->tenantIds));

        return DB::transaction(function () use ($unit, $data, $tenantIds) {
            $unit = Unit::lockForUpdate()->findOrFail($unit->id);

            abort_if($unit->status === UnitStatus::Maintenance, 422, __('This unit is under maintenance and cannot be leased.'));

            $existingLease = $unit->leases()->where('status', 'active')->first();
            $activeTenantsCount = $this->occupancy->activeOccupantCount($unit);

            if ($existingLease) {
                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');
                $newTenantIds = array_diff($tenantIds, $existingTenantIds->all());

                abort_if(! $this->occupancy->canAccommodate($unit, count($newTenantIds)), 422, __('Unit capacity exceeded. Unit can only hold :capacity occupants.', ['capacity' => $unit->capacity]));

                foreach ($newTenantIds as $tenantId) {
                    $existingLease->tenants()->attach($tenantId, ['is_primary' => false]);
                }

                $unit->update(['status' => UnitStatus::Occupied]);

                return $existingLease;
            }

            abort_if(! $this->occupancy->canAccommodate($unit, count($tenantIds)), 422, __('Unit capacity exceeded. Unit can only hold :capacity occupants.', ['capacity' => $unit->capacity]));

            $unitRate = $data->unitRateId ? UnitRate::find($data->unitRateId) : null;
            $rentAmount = $data->rentAmount ?? $unitRate?->amount ?? $unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount');
            $isCustomPrice = $data->rentAmount !== null && $unitRate && (float) $data->rentAmount !== (float) $unitRate->amount;

            $primaryTenantId = $tenantIds[0];

            $lease = $unit->leases()->create([
                'primary_tenant_id' => $primaryTenantId,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'rent_amount' => $rentAmount,
                'billing_interval' => $data->billingInterval ?? $unitRate?->billing_interval ?? 1,
                'billing_unit' => $data->billingUnit ?? $unitRate?->billing_unit ?? 'month',
                'is_custom_price' => $isCustomPrice,
                'unit_rate_id' => $data->unitRateId,
                'deposit_amount' => $data->depositAmount ?? 0,
                'deposit_paid_at' => $data->depositPaidAt,
                'deposit_refund_amount' => $data->depositRefundAmount,
                'deposit_refunded_at' => $data->depositRefundedAt,
                'rent_due_day' => $data->rentDueDay ?? 1,
                'status' => 'active',
                'notes' => $data->notes,
            ]);

            foreach ($tenantIds as $index => $tenantId) {
                $lease->tenants()->attach($tenantId, ['is_primary' => $index === 0]);
            }

            $unit->update(['status' => UnitStatus::Occupied]);

            return $lease;
        });
    }
}
