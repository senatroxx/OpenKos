<?php

namespace App\Actions\Leases;

use App\Actions\Invoices\GenerateInvoices;
use App\Business\Leases\LeaseFinancialChecker;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\RenewalEligibilityChecker;
use App\Data\Lease\RenewLeaseData;
use App\Enums\LeaseStatus;
use App\Exceptions\LeaseRenewalException;
use App\Models\Lease;
use App\Models\Unit;
use App\Results\Lease\RenewLeaseResult;
use App\Services\Payments\MoneyConverter;
use Illuminate\Support\Facades\DB;

class RenewLease
{
    public function __construct(
        private readonly RenewalEligibilityChecker $eligibility,
        private readonly LeaseFinancialChecker $financial,
        private readonly LeaseStatusValidator $leaseStatusValidator,
        private readonly GenerateInvoices $generateInvoices,
        private readonly MoneyConverter $money,
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
                'Lease has an outstanding balance of '.$outstanding['balance'].'. Confirm to proceed.'
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

            try {
                $rentAmount = $this->money->normalizeAmount($data->rentAmount, $lease->currency);
                $depositAmount = $lease->deposit_amount === null
                    ? null
                    : $this->money->normalizeAmount((string) $lease->deposit_amount, $lease->currency);
            } catch (\InvalidArgumentException) {
                return RenewLeaseResult::error('The existing lease amount is invalid for its currency.');
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
                'rent_amount' => $rentAmount,
                'currency' => $lease->currency,
                'deposit_amount' => $depositAmount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'billing_interval' => $lease->billing_interval,
                'billing_unit' => $lease->billing_unit,
                'billing_strategy' => $lease->billing_strategy,
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

            $this->generateInvoices->execute($newLease);

            return RenewLeaseResult::success($newLease);
        });

        return $result;
    }
}
