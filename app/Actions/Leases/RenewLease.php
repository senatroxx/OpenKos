<?php

namespace App\Actions\Leases;

use App\Business\Leases\LeaseFinancialChecker;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\RenewalEligibilityChecker;
use App\Data\Lease\RenewLeaseData;
use App\Enums\LeaseStatus;
use App\Events\Lease\LeaseStatusChanged;
use App\Exceptions\LeaseRenewalException;
use App\Models\Lease;
use App\Models\Unit;
use App\Results\Lease\RenewLeaseResult;
use Illuminate\Support\Facades\DB;

class RenewLease
{
    public function __construct(
        private readonly RenewalEligibilityChecker $eligibility,
        private readonly LeaseFinancialChecker $financial,
        private readonly LeaseStatusValidator $leaseStatusValidator,
    ) {}

    public function execute(Lease $lease, RenewLeaseData $data): RenewLeaseResult
    {
        try {
            $this->eligibility->ensureCanRenew($lease);
        } catch (LeaseRenewalException $e) {
            return RenewLeaseResult::error($e->getMessage());
        }

        $outstanding = $this->financial->outstandingCheck($lease);

        if ($outstanding['hasOutstanding'] && ! $data->confirmedOutstanding) {
            return RenewLeaseResult::error(
                'Lease has an outstanding balance of '.number_format($outstanding['balance'] / 100, 2).'. Confirm to proceed.'
            );
        }

        $oldStatus = $lease->status;

        $result = DB::transaction(function () use ($lease, $data, $oldStatus) {
            $unit = Unit::lockForUpdate()->findOrFail($lease->unit_id);

            $this->leaseStatusValidator->validate($oldStatus, LeaseStatus::Renewed);

            $existingActive = $unit->leases()
                ->where('status', LeaseStatus::Active->value)
                ->where('id', '!=', $lease->id)
                ->exists();

            if ($existingActive) {
                return RenewLeaseResult::error('Unit already has an active lease.');
            }

            $lease->update([
                'status' => LeaseStatus::Renewed,
            ]);

            $newEndDate = $data->endDate;

            $newLease = $unit->leases()->create([
                'previous_lease_id' => $lease->id,
                'primary_tenant_id' => $lease->primary_tenant_id,
                'start_date' => $lease->end_date->addDay(),
                'end_date' => $newEndDate,
                'rent_amount' => $data->rentAmount,
                'deposit_amount' => $lease->deposit_amount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'billing_interval' => $lease->billing_interval,
                'billing_unit' => $lease->billing_unit,
                'rent_due_day' => $lease->rent_due_day,
                'is_custom_price' => $lease->is_custom_price,
                'unit_rate_id' => $lease->unit_rate_id,
                'status' => LeaseStatus::Active,
            ]);

            foreach ($lease->tenants as $tenant) {
                $newLease->tenants()->attach($tenant->id, [
                    'is_primary' => $tenant->id === $lease->primary_tenant_id,
                ]);
            }

            $newLease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

            return RenewLeaseResult::success($newLease);
        });

        if ($result->succeeded()) {
            LeaseStatusChanged::dispatch($lease, $oldStatus, LeaseStatus::Renewed);
        }

        return $result;
    }
}
